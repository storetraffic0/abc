// /js/clicterra-loader.js - Skrip Penghubung untuk AdPlayer.js dan Backend Clicterra

(function() {
    // Pastikan antrian permintaan sudah ada
    var adQueue = window.ClicterraAds = window.ClicterraAds || [];
    var adServerBaseUrl = '//ssp.svradv.com/api/get_ad.php'; // Gunakan URL protocol-relative

    /**
     * Fungsi utama untuk memproses satu permintaan iklan.
     * @param {object} config - Objek konfigurasi berisi {zoneId, container}.
     */
    function processAdRequest(config) {
        if (!config.zoneId || !config.container) {
            console.error('Clicterra Loader: zoneId or container ID is missing from the ad request.');
            return;
        }

        const requestUrl = `${adServerBaseUrl}?zone_id=${config.zoneId}`;

        // 1. Panggil backend Anda untuk mendapatkan VAST URL dinamis
        fetch(requestUrl)
            .then(response => {
                if (response.status === 204) throw new Error('No ad available (204 No Content).');
                if (!response.ok) throw new Error(`Network error: ${response.statusText}`);
                return response.json();
            })
            .then(adData => {
                if (adData && adData.vastUrl) {
                    // 2. Jika VAST URL diterima, buat pemutar iklan
                    createAdPlayer(config.container, adData.vastUrl);
                } else {
                    throw new Error('Valid VAST URL not found in the ad data.');
                }
            })
            .catch(error => {
                console.error(`Clicterra Loader (Zone ${config.zoneId}):`, error.message);
                // Sembunyikan container jika tidak ada iklan agar tidak ada ruang kosong
                const playerElement = document.getElementById(config.container);
                if (playerElement) playerElement.style.display = 'none';
            });
    }

    /**
     * Membuat instance AdPlayer.js dengan konfigurasi yang benar.
     * @param {string} containerId - ID dari elemen div tempat player akan dibuat.
     * @param {string} dynamicVastUrl - VAST URL yang didapat dari backend Anda.
     */
    function createAdPlayer(containerId, dynamicVastUrl) {
        const playerElement = document.getElementById(containerId);
        if (!playerElement) {
            console.error(`Clicterra Loader: Ad container element not found: #${containerId}`);
            return;
        }

        // 3. Konfigurasi player dengan VAST URL dinamis dari sistem Anda
        const player = new adserve.tv.Player(playerElement, {
            width: '100%',
            height: '100%',
            src: 'https://video.rmhfrtnd.com/production/prerolls/oil-show11.mp4', // Video konten dummy (wajib ada)
            autoplay: 'muted',
            controls: false,
            ads: {
                enabled: true,
                desktop: {
                    inView: { preroll: true, vastUrl: dynamicVastUrl },
                    notInView: { preroll: true, vastUrl: dynamicVastUrl }
                },
                mobile: {
                    inView: { preroll: true, vastUrl: dynamicVastUrl }
                }
            },
        }, function() {
            // Callback setelah player siap
            console.log(`Clicterra: Ad player initialized for container #${containerId} with VAST from your system.`);
        });

        player.addEventListener('PlayerError', function(message) {
            console.error(`Clicterra Ad Player Error (Container #${containerId}):`, message);
            playerElement.style.display = 'none'; // Sembunyikan jika error
        });
    }

    /**
     * Fungsi untuk memulai seluruh proses.
     * Menunggu hingga library adplayer.js (adserve.tv) sepenuhnya dimuat.
     */
    function initialize() {
        // Proses semua permintaan yang mungkin sudah ada di antrian
        while (adQueue.length > 0) {
            processAdRequest(adQueue.shift());
        }
        // Ganti fungsi .push() agar permintaan baru langsung diproses
        adQueue.push = processAdRequest;
    }

    // Cek apakah library adplayer.js sudah dimuat.
    if (typeof adserve !== 'undefined' && typeof adserve.tv !== 'undefined') {
        // Jika sudah, langsung jalankan.
        initialize();
    } else {
        // Jika belum, tambahkan event listener untuk menunggu.
        // Anda mungkin perlu memastikan bahwa file adplayer.js Anda memicu event 'adplayer-loaded' saat selesai dimuat.
        // Jika tidak, cara yang lebih aman adalah dengan interval check.
        const checkInterval = setInterval(function() {
            if (typeof adserve !== 'undefined' && typeof adserve.tv !== 'undefined') {
                clearInterval(checkInterval);
                initialize();
            }
        }, 100); // Cek setiap 100ms
    }

})();