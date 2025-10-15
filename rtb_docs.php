<?php
// Set a default timezone to avoid potential warnings
date_default_timezone_set('UTC');

// Data for personalization
$last_updated_date = "2025-10-07"; // You can make this dynamic if you want
$contact_name = "Simon (simoncode12)";
$platform_name = "Clicterra";

// Helper function to escape HTML
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTB Integration Documentation - <?php echo e($platform_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            position: relative;
            background-color: #f8f9fa;
        }
        .sidebar {
            position: sticky;
            top: 1rem;
            height: calc(100vh - 2rem);
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: #6c757d;
        }
        .sidebar .nav-link.active,
        .sidebar .nav-link:hover {
            color: #0d6efd;
        }
        section {
            padding-top: 5rem;
            margin-top: -4rem;
        }
        h2 {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        h4 {
            margin-top: 2rem;
        }
        code {
            background-color: #e9ecef;
            padding: 0.2em 0.4em;
            border-radius: 3px;
            font-size: 0.9em;
        }
        pre {
            background-color: #212529;
            color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.3rem;
            position: relative;
        }
        pre code {
            background-color: transparent;
            padding: 0;
            color: inherit;
        }
        .copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            cursor: pointer;
            color: #adb5bd;
            border: none;
            background: none;
        }
        .copy-btn:hover {
            color: #fff;
        }
        .table th {
            white-space: nowrap;
        }
        .badge {
            font-size: 0.75em;
        }
    </style>
</head>
<body data-bs-spy="scroll" data-bs-target="#sidebarNav">

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav id="sidebarNav" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky pt-3">
                <h5 class="px-3"><?php echo e($platform_name); ?> RTB Docs</h5>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="#summary">Summary</a></li>
                    <li class="nav-item"><a class="nav-link" href="#endpoint">Bid Request Endpoint</a></li>
                    <li class="nav-item"><a class="nav-link" href="#request-format">Bid Request Format</a></li>
                    <li class="nav-item"><a class="nav-link" href="#response-format">Bid Response Format</a></li>
                    <li class="nav-item"><a class="nav-link" href="#win-notification">Win Notification</a></li>
                    <li class="nav-item"><a class="nav-link" href="#workflow-example">Full Workflow Example</a></li>
                    <li class="nav-item"><a class="nav-link" href="#support">Support</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">RTB Integration Documentation for Publishers & SSPs</h1>
                <p class="text-muted">Last Updated: <?php echo date("F j, Y", strtotime($last_updated_date)); ?></p>
            </div>

            <section id="summary">
                <h2>1. Summary</h2>
                <p>This document provides all technical information required to integrate your supply platform (SSP or direct publisher) with the <?php echo e($platform_name); ?> Real-Time Bidding (RTB) endpoint.</p>
                <p>Our RTB endpoint is designed to receive bid requests in the <strong>OpenRTB 2.5</strong> format. In response, we will return a bid response with a CPM price and a VAST XML creative if we have a matching campaign, or a "No Bid" response otherwise.</p>
                <p>The process consists of three main steps:</p>
                <ol>
                    <li><strong>Bid Request:</strong> Your SSP sends details about an ad opportunity (impression) to our endpoint.</li>
                    <li><strong>Bid Response:</strong> Our server runs an internal auction and responds with a price bid (or no bid).</li>
                    <li><strong>Win Notification:</strong> If our bid wins the auction on your side, you are <strong>required</strong> to call our win notification URL to ensure correct billing and statistics.</li>
                </ol>
            </section>

            <section id="endpoint">
                <h2>2. Bid Request Endpoint</h2>
                <p>All bid requests must be sent to the following endpoint:</p>
                <dl class="row">
                    <dt class="col-sm-3">URL</dt>
                    <dd class="col-sm-9"><code>https://adserver.com/ssp.php</code></dd>

                    <dt class="col-sm-3">Method</dt>
                    <dd class="col-sm-9"><span class="badge bg-primary">POST</span></dd>

                    <dt class="col-sm-3">Content Format</dt>
                    <dd class="col-sm-9"><code>application/json</code></dd>

                    <dt class="col-sm-3">Required Headers</dt>
                    <dd class="col-sm-9">
                        <code>Content-Type: application/json</code><br>
                        <code>x-openrtb-version: 2.5</code>
                    </dd>

                    <dt class="col-sm-3">Timeout Recommendation</dt>
                    <dd class="col-sm-9">We recommend setting a response timeout on your end between <strong>200-300ms</strong>. Our server is optimized to respond well below this threshold.</dd>
                </dl>
            </section>

            <section id="request-format">
                <h2>3. Bid Request Format</h2>
                <p>We support a subset of the OpenRTB 2.5 specification. The following fields are required or highly recommended to get the best auction results.</p>
                
                <h4>Main Request Object</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Field</th><th>Type</th><th>Description</th><th>Example</th></tr></thead>
                        <tbody>
                            <tr><td><code>id</code></td><td>string</td><td><strong>Required.</strong> A unique ID for this bid request.</td><td><code>"abc-123-xyz-456"</code></td></tr>
                            <tr><td><code>imp</code></td><td>array</td><td><strong>Required.</strong> An array of Impression objects. Send only one object per request.</td><td><code>[{...}]</code></td></tr>
                            <tr><td><code>site</code></td><td>object</td><td><strong>Required.</strong> A Site object describing the publisher's site.</td><td><code>{...}</code></td></tr>
                            <tr><td><code>device</code></td><td>object</td><td><strong>Required.</strong> A Device object describing the user's device.</td><td><code>{...}</code></td></tr>
                            <tr><td><code>user</code></td><td>object</td><td>Recommended. A User object describing the user.</td><td><code>{...}</code></td></tr>
                        </tbody>
                    </table>
                </div>

                <h4>The <code>site</code> Object</h4>
                 <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Field</th><th>Type</th><th>Description</th><th>Example</th></tr></thead>
                        <tbody>
                            <tr><td><code>id</code></td><td>string</td><td>Recommended. A unique ID for the site on your platform.</td><td><code>"site-9876"</code></td></tr>
                            <tr><td><code>domain</code></td><td>string</td><td><strong>Required.</strong> The domain of the site (e.g., without http:// or www.).</td><td><code>"examplepublisher.com"</code></td></tr>
                            <tr><td><code>cat</code></td><td>array</td><td>Recommended. An array of IAB content categories.</td><td><code>["IAB2-2", "IAB9-30"]</code></td></tr>
                            <tr><td><code>ext.idzone</code></td><td>integer</td><td><strong>Required.</strong> The unique Zone ID you created in the <?php echo e($platform_name); ?> platform. This is a key field for identification.</td><td><code>445566</code></td></tr>
                        </tbody>
                    </table>
                </div>

                <h4>The <code>device</code> Object</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Field</th><th>Type</th><th>Description</th><th>Example</th></tr></thead>
                        <tbody>
                            <tr><td><code>ua</code></td><td>string</td><td><strong>Required.</strong> The full User-Agent string from the user's browser.</td><td><code>"Mozilla/5.0 (Windows..."</code></td></tr>
                            <tr><td><code>ip</code></td><td>string</td><td><strong>Required.</strong> The user's public IPv4 or IPv6 address.</td><td><code>"180.252.1.1"</code></td></tr>
                            <tr><td><code>geo.country</code></td><td>string</td><td>Recommended. The ISO 3166-1 alpha-2 country code.</td><td><code>"ID"</code></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="response-format">
                <h2>4. Bid Response Format</h2>
                <p>If we have a matching campaign, you will receive an <code>HTTP 200 OK</code> response with the following JSON format. If we have no matching ad, you will receive an <code>HTTP 204 No Content</code> response.</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>id</code></td><td>string</td><td>The ID of the original Bid Request.</td></tr>
                            <tr><td><code>seatbid[0].bid[0].price</code></td><td>float</td><td>The <strong>CPM price</strong> we are bidding for this impression.</td></tr>
                            <tr><td><code>seatbid[0].bid[0].adm</code></td><td>string</td><td>The <strong>VAST XML</strong> creative as a string. You must render this content if our bid wins.</td></tr>
                            <tr><td><code>seatbid[0].bid[0].cid</code></td><td>string</td><td>Our internal Campaign ID.</td></tr>
                            <tr><td><code>seatbid[0].bid[0].crid</code></td><td>string</td><td>Our internal Creative ID.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="win-notification">
                <h2>5. Win Notification</h2>
                <p>This is a <strong>mandatory</strong> step. If our bid wins the auction on your side, you must call our win notification URL. This is critical for accurate reporting and billing.</p>
                 <dl class="row">
                    <dt class="col-sm-3">Endpoint</dt>
                    <dd class="col-sm-9"><code>https://adserver.com/api/track.php</code></dd>
                    <dt class="col-sm-3">Event</dt>
                    <dd class="col-sm-9"><code>win</code></dd>
                    <dt class="col-sm-3">Method</dt>
                    <dd class="col-sm-9"><span class="badge bg-secondary">GET</span> (Pixel Call)</dd>
                </dl>
                
                <h4>Required Parameters</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Parameter</th><th>Description</th><th>Example</th></tr></thead>
                        <tbody>
                            <tr><td><code>event</code></td><td>Always must be <code>win</code>.</td><td><code>win</code></td></tr>
                            <tr><td><code>price</code></td><td><strong>Winning Price (CPM)</strong>. The actual price from your auction.</td><td><code>1.25</code> (for $1.25 CPM)</td></tr>
                            <tr><td><code>cid</code></td><td>Our Campaign ID, from the Bid Response (<code>seatbid.bid.cid</code>).</td><td><code>101</code></td></tr>
                            <tr><td><code>sid</code></td><td>Your Site ID.</td><td><code>site-9876</code></td></tr>
                            <tr><td><code>zid</code></td><td>The <?php echo e($platform_name); ?> Zone ID.</td><td><code>445566</code></td></tr>
                            <tr><td><code>impid</code></td><td>The original Impression ID from the Bid Request.</td><td><code>"abc-123-xyz-456"</code></td></tr>
                        </tbody>
                    </table>
                </div>

                <h4>Full Win Notification URL Example</h4>
                <pre><button class="copy-btn" title="Copy to clipboard"><i class="bi bi-clipboard"></i></button><code>https://adserver.com/api/track.php?event=win&price=1.25&cid=101&sid=site-9876&zid=445566&impid=abc-123-xyz-456</code></pre>
            </section>
            
            <section id="workflow-example">
                <h2>6. Full Workflow Example</h2>
                
                <h4>Step 1: SSP Sends Bid Request</h4>
                <pre><button class="copy-btn" title="Copy to clipboard"><i class="bi bi-clipboard"></i></button><code>POST /ssp.php
{
  "id": "ssp-req-998877",
  "imp": [{"id": "1"}],
  "site": {
    "id": "site-9876",
    "domain": "coolvideos.com",
    "ext": { "idzone": 445566 }
  },
  "device": {
    "ua": "Mozilla/5.0 (Windows NT 10.0; ...)",
    "ip": "180.252.1.1",
    "geo": { "country": "ID" }
  }
}</code></pre>

                <h4>Step 2: <?php echo e($platform_name); ?> Responds with a Bid</h4>
                <pre><button class="copy-btn" title="Copy to clipboard"><i class="bi bi-clipboard"></i></button><code>HTTP 200 OK
{
  "id": "ssp-req-998877",
  "seatbid": [{
    "bid": [{
      "price": 1.50,
      "adm": "&lt;?xml ... VAST XML content ... &gt;",
      "cid": "101",
      "crid": "crid-101"
    }]
  }]
}</code></pre>

                <h4>Step 3: <?php echo e($platform_name); ?>'s Bid Wins at a price of $1.45 CPM.</h4>
                <p>The SSP's internal auction concludes, and our bid is the winner.</p>

                <h4>Step 4: SSP Calls the Win Notification URL</h4>
                <pre><button class="copy-btn" title="Copy to clipboard"><i class="bi bi-clipboard"></i></button><code>GET /api/track.php?event=win&price=1.45&cid=101&sid=site-9876&zid=445566&impid=ssp-req-998877</code></pre>
            </section>
            
            <section id="support">
                <h2>7. Support</h2>
                <p>If you have any further questions or require technical assistance during integration, please do not hesitate to contact our technical operations team.</p>
                <p><strong>Contact:</strong> <?php echo e($contact_name); ?><br>
                Head of Technical Operations<br>
                <?php echo e($platform_name); ?></p>
            </section>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Activate scrollspy
        var scrollSpy = new bootstrap.ScrollSpy(document.body, {
            target: '#sidebarNav',
            offset: 100
        });

        // Add copy-to-clipboard functionality to all copy buttons
        document.querySelectorAll('.copy-btn').forEach(button => {
            button.addEventListener('click', function () {
                const pre = this.parentElement;
                const code = pre.querySelector('code');
                navigator.clipboard.writeText(code.innerText).then(() => {
                    const originalIcon = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check-lg"></i>';
                    setTimeout(() => {
                        this.innerHTML = originalIcon;
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                });
            });
        });
    });
</script>

</body>
</html>