<?php
// Previous code remains the same

// Script that will be sent to the browser
?>

// Banner ad loader
(function() {
    // Configuration
    const zoneId = <?php echo $zone_id; ?>;
    const width = <?php echo $width; ?>;
    const height = <?php echo $height; ?>;
    const containerId = 'banner-container-' + zoneId;
    
    // Create container if it doesn't exist
    if (!document.getElementById(containerId)) {
        const container = document.createElement('div');
        container.id = containerId;
        container.style.width = width + 'px';
        container.style.height = height + 'px';
        document.currentScript.parentNode.insertBefore(container, document.currentScript);
    }
    
    // Logging
    console.log('Loading banner for zone: ' + zoneId + ' (' + width + 'x' + height + ')');
    
    // Helper functions
    function loadBanner(html) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = html;
            console.log('Banner loaded successfully');
            
            // Execute any scripts in the banner HTML
            const scripts = container.querySelectorAll('script');
            scripts.forEach(oldScript => {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        } else {
            console.error('Banner container not found: ' + containerId);
        }
    }
    
    // Make request to ad server
    function requestAd() {
        fetch('<?php echo $APP_SETTINGS['ad_tag_domain'] ?? ""; ?>/banner_ad.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                zone_id: zoneId,
                width: width,
                height: height,
                domain: '<?php echo $domain; ?>',
                token: '<?php echo $token; ?>',
                referrer: document.referrer,
                url: window.location.href
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                loadBanner(data.html);
                console.log('Ad content received and loaded');
            } else {
                console.log('No ad available for this zone');
                loadBanner('<a href="https://clicterra.com" target="_blank"><img src="<?php echo $APP_SETTINGS['ad_tag_domain'] ?? ""; ?>/default_banners/'+width+'x'+height+'.jpg" width="'+width+'" height="'+height+'" alt="Ad" style="border:0"></a>');
            }
        })
        .catch(error => {
            console.error('Error fetching ad:', error);
            loadBanner('<a href="https://clicterra.com" target="_blank"><img src="<?php echo $APP_SETTINGS['ad_tag_domain'] ?? ""; ?>/default_banners/'+width+'x'+height+'.jpg" width="'+width+'" height="'+height+'" alt="Ad" style="border:0"></a>');
        });
    }
    
    // Start loading the ad
    requestAd();
})();