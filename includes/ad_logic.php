<?php
// /includes/ad_logic.php - VERSI KINERJA TINGGI FINAL (dengan cURL Asinkron & Caching Redis)

if (!function_exists('find_eligible_campaign')) {
    /**
     * Menemukan kampanye yang paling sesuai dengan permintaan RTB.
     * Menggunakan Caching untuk mengurangi beban database dan cURL Asinkron untuk kecepatan.
     */
    function find_eligible_campaign($rtb_request) {
        global $pdo;
        if (!$pdo) { $pdo = get_db_connection(); }
        $APP_SETTINGS = load_app_settings();
        $AD_TAG_DOMAIN = $APP_SETTINGS['ad_tag_domain'] ?? ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']);

        $zone_id = $rtb_request['site']['ext']['idzone'] ?? 0;
        $country_alpha2 = $rtb_request['device']['geo']['country'] ?? 'XX';
        $country_for_query = country_code_alpha2_to_alpha3($country_alpha2);
        
        // =================================================================
        // OPTIMISASI KINERJA: Menerapkan Caching untuk query kampanye
        // =================================================================
        $eligible_campaigns = null;
        $redis = get_redis_connection();
        $cache_key = "vast_campaigns:" . $country_for_query;

        // 1. Coba ambil dari cache terlebih dahulu
        if ($redis) {
            $cached_campaigns = $redis->get($cache_key);
            if ($cached_campaigns) {
                $eligible_campaigns = json_decode($cached_campaigns, true);
            }
        }

        // 2. Jika tidak ada di cache (atau Redis mati), baru query ke database
        if ($eligible_campaigns === null) {
            $sql = "SELECT c.id, c.advertiser_id, c.campaign_type, c.priority, c.cpm_rate, 
                           cd.third_party_vast_url, cd.rtb_endpoint_url
                    FROM campaigns c 
                    JOIN campaign_details cd ON c.id = cd.campaign_id 
                    JOIN campaign_targeting ct ON c.id = ct.campaign_id
                    WHERE c.status = 'active'
                      AND (c.start_date IS NULL OR c.start_date <= CURDATE()) 
                      AND (c.end_date IS NULL OR c.end_date >= CURDATE()) 
                      AND (JSON_LENGTH(ct.countries) = 0 OR JSON_CONTAINS(ct.countries, JSON_QUOTE(:country)))
                      AND c.ad_format = 'vast'
                    ORDER BY c.priority DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':country' => $country_for_query]);
            $eligible_campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Simpan hasil ke cache untuk 60 detik jika Redis aktif dan ada hasil
            if ($redis && !empty($eligible_campaigns)) {
                $redis->set($cache_key, json_encode($eligible_campaigns), 60); // TTL (Time To Live) = 60 detik
            }
        }
        
        // Jika setelah semua usaha tidak ada kampanye, keluar.
        if (empty($eligible_campaigns)) {
             return ['type' => 'xml', 'content' => '<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>'];
        }

        // --- Sisa logika di bawah ini sama persis seperti versi perbaikan sebelumnya ---
        
        $user_agent = $rtb_request['device']['ua'] ?? '';
        $device_os = 'Unknown'; if (stripos($user_agent, 'Windows') !== false) $device_os = 'Windows'; elseif (stripos($user_agent, 'Macintosh') !== false) $device_os = 'macOS'; elseif (stripos($user_agent, 'Android') !== false) $device_os = 'Android'; elseif (stripos($user_agent, 'iPhone') !== false || stripos($user_agent, 'iPad') !== false) $device_os = 'iOS'; elseif (stripos($user_agent, 'Linux') !== false) $device_os = 'Linux';
        $device_type = 'Desktop'; if (stripos($user_agent, 'Mobi') !== false) $device_type = 'Mobile'; elseif (stripos($user_agent, 'Tablet') !== false) $device_type = 'Tablet';
        $browser = 'Unknown'; if (stripos($user_agent, 'Edg') !== false) $browser = 'Edge'; elseif (stripos($user_agent, 'Chrome') !== false && !stripos($user_agent, 'Chromium')) $browser = 'Chrome'; elseif (stripos($user_agent, 'Safari') !== false && !stripos($user_agent, 'Chrome')) $browser = 'Safari'; elseif (stripos($user_agent, 'Firefox') !== false) $browser = 'Firefox'; elseif (stripos($user_agent, 'OPR') !== false || stripos($user_agent, 'Opera') !== false) $browser = 'Opera';
        $parsed_device_info = ['os' => $device_os, 'type' => $device_type, 'browser' => $browser];

        $bids = [];
        $external_campaigns_to_call = [];

        foreach ($eligible_campaigns as $campaign) {
            if ($campaign['campaign_type'] === 'internal' && !empty($campaign['third_party_vast_url'])) {
                $bids[] = ['price' => (float)$campaign['cpm_rate'], 'ad_material' => $campaign['third_party_vast_url'], 'campaign' => $campaign, 'source' => 'internal'];
            } elseif ($campaign['campaign_type'] === 'external' && !empty($campaign['rtb_endpoint_url'])) {
                $external_campaigns_to_call[] = $campaign;
            }
        }
        
        if (!empty($external_campaigns_to_call)) {
            $external_bids = send_parallel_rtb_requests($external_campaigns_to_call, $rtb_request);
            if (!empty($external_bids)) {
                $bids = array_merge($bids, $external_bids);
            }
        }

        if (empty($bids)) {
            return ['type' => 'xml', 'content' => '<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"></VAST>'];
        }

        usort($bids, function($a, $b) {
            $priority_a = $a['campaign']['priority'] ?? 5; $priority_b = $b['campaign']['priority'] ?? 5;
            if ($priority_a !== $priority_b) { return $priority_b <=> $priority_a; }
            $price_a = $a['price']; $price_b = $b['price'];
            if ($price_a !== $price_b) { return $price_b <=> $price_a; }
            return 0;
        });
        
        $top_bid_price = $bids[0]['price'];
        $top_bid_priority = $bids[0]['campaign']['priority'] ?? 5;
        $top_tier_bidders = [];
        foreach ($bids as $bid) {
            if (($bid['campaign']['priority'] ?? 5) === $top_bid_priority && $bid['price'] === $top_bid_price) {
                $top_tier_bidders[] = $bid;
            } else { break; }
        }
        
        $winner = $top_tier_bidders[array_rand($top_tier_bidders)];

        $winning_campaign = $winner['campaign'];
        $winning_campaign['final_cpm'] = $winner['price'];
        $impression_id = uniqid('imp_');

        $common_params = [
            'cid' => $winning_campaign['id'], 'zid' => $zone_id, 
            'sid' => $rtb_request['site']['id'] ?? 0, 'cc' => $country_for_query, 
            'impid' => $impression_id, 'uid' => $rtb_request['user']['id'] ?? '', 
            'os' => $parsed_device_info['os'], 'dev' => $parsed_device_info['type'], 'brw' => $parsed_device_info['browser']
        ];
        
        $impression_tracker_url = rtrim($AD_TAG_DOMAIN, '/') . '/api/track.php?' . http_build_query(array_merge($common_params, ['event' => 'impression', 'price' => $winning_campaign['final_cpm']]));
        $click_tracker_url = rtrim($AD_TAG_DOMAIN, '/') . '/api/track.php?' . http_build_query(array_merge($common_params, ['event' => 'click']));

        if ($winner['source'] === 'internal') {
            $vast_xml = generate_simple_wrapper($winning_campaign['id'], $winner['ad_material'], $impression_tracker_url, $click_tracker_url);
        } else {
            $vast_xml_with_impression = inject_impression_tracker_to_vast($winner['ad_material'], $impression_tracker_url);
            $vast_xml = inject_click_tracker_to_vast($vast_xml_with_impression, $click_tracker_url);
        }

        return ['type' => 'json', 'content' => [
            'type' => 'bid', 'price' => (float)$winning_campaign['final_cpm'], 'adm' => $vast_xml,
            'cid' => (string)$winning_campaign['id'], 'crid' => 'crid-' . $winning_campaign['id']
        ]];
    }
}

if (!function_exists('send_parallel_rtb_requests')) {
    function send_parallel_rtb_requests($campaigns, $original_request) {
        $mh = curl_multi_init();
        $curl_handles = [];
        $campaign_map = [];
        $bids = [];

        $request_body = json_encode($original_request);
        $headers = ['Content-Type: application/json', 'x-openrtb-version: 2.5'];

        foreach ($campaigns as $campaign) {
            $ch = curl_init($campaign['rtb_endpoint_url']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $request_body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT_MS => 200,
                CURLOPT_CONNECTTIMEOUT_MS => 100
            ]);
            $curl_handles[] = $ch;
            $campaign_map[(int)$ch] = $campaign;
            curl_multi_add_handle($mh, $ch);
        }

        $active = null;
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($mh) != -1) {
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach ($curl_handles as $ch) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http_code === 200) {
                $response_body = curl_multi_getcontent($ch);
                if ($response_body) {
                    $response_data = json_decode($response_body, true);
                    if (isset($response_data['seatbid'][0]['bid'][0])) {
                        $bid = $response_data['seatbid'][0]['bid'][0];
                        $campaign = $campaign_map[(int)$ch];
                        if (!empty($bid['adm']) && isset($bid['price']) && (float)$bid['price'] > 0) {
                            $bids[] = [
                                'price' => (float)$bid['price'],
                                'ad_material' => $bid['adm'],
                                'campaign' => $campaign,
                                'source' => 'external'
                            ];
                        }
                    }
                }
            }
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $bids;
    }
}


if (!function_exists('inject_impression_tracker_to_vast')) {
    function inject_impression_tracker_to_vast($vast_xml, $impression_url) {
        if (empty($vast_xml) || strpos($vast_xml, '<VAST') === false) { return $vast_xml; }
        $dom = new DOMDocument(); $dom->preserveWhiteSpace = false; $dom->formatOutput = true;
        try { @$dom->loadXML($vast_xml); } catch (Exception $e) { error_log("Failed to parse VAST XML: " . $e->getMessage()); return $vast_xml; }
        $xpath = new DOMXPath($dom);
        $ad_elements = $xpath->query('//Ad');
        foreach ($ad_elements as $ad) {
            $target_elements = $xpath->query('.//InLine | .//Wrapper', $ad);
            foreach ($target_elements as $target) {
                $impression = $dom->createElement('Impression');
                $impression->appendChild($dom->createCDATASection($impression_url));
                $target->appendChild($impression);
            }
        }
        return $dom->saveXML();
    }
}

if (!function_exists('inject_click_tracker_to_vast')) {
    function inject_click_tracker_to_vast($vast_xml, $click_url) {
        if (empty($vast_xml) || strpos($vast_xml, '<VAST') === false) { return $vast_xml; }
        $dom = new DOMDocument(); $dom->preserveWhiteSpace = false; $dom->formatOutput = true;
        try { @$dom->loadXML($vast_xml); } catch (Exception $e) { error_log("Failed to parse VAST XML: " . $e->getMessage()); return $vast_xml; }
        $xpath = new DOMXPath($dom);
        $linear_elements = $xpath->query('//Creative/Linear');
        foreach ($linear_elements as $linear) {
            $video_clicks_elements = $xpath->query('.//VideoClicks', $linear);
            $video_clicks = null;
            if ($video_clicks_elements->length > 0) {
                $video_clicks = $video_clicks_elements->item(0);
            } else {
                $video_clicks = $dom->createElement('VideoClicks');
                $linear->appendChild($video_clicks);
            }
            $click_tracking = $dom->createElement('ClickTracking');
            $click_tracking->appendChild($dom->createCDATASection($click_url));
            $video_clicks->appendChild($click_tracking);
        }
        return $dom->saveXML();
    }
}

if (!function_exists('generate_simple_wrapper')) {
    function generate_simple_wrapper($campaign_id, $vast_tag_uri, $impression_url, $click_url) {
        $impression_node = '<Impression><![CDATA[' . htmlspecialchars($impression_url, ENT_XML1, 'UTF-8') . ']]></Impression>';
        $click_node = '<ClickTracking><![CDATA[' . htmlspecialchars($click_url, ENT_XML1, 'UTF-8') . ']]></ClickTracking>';
        return '<?xml version="1.0" encoding="UTF-8"?><VAST version="3.0"><Ad id="'.$campaign_id.'"><Wrapper><AdSystem>Clicterra AdServer</AdSystem><VASTAdTagURI><![CDATA['.$vast_tag_uri.']]></VASTAdTagURI>'.$impression_node.'<Creatives><Creative><Linear><VideoClicks>'.$click_node.'</VideoClicks></Linear></Creative></Creatives></Wrapper></Ad></VAST>';
    }
}

if (!function_exists('send_rtb_request')) {
    function send_rtb_request($endpoint_url, $original_request) {
        $ch = curl_init($endpoint_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($original_request),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-openrtb-version: 2.5'],
            CURLOPT_TIMEOUT_MS => 200
        ]);
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response_body) {
            $response_data = json_decode($response_body, true);
            if (isset($response_data['seatbid'][0]['bid'][0])) {
                $bid = $response_data['seatbid'][0]['bid'][0];
                return ['price' => (float)$bid['price'], 'adm' => $bid['adm']];
            }
        }
        return null;
    }
}

?>

