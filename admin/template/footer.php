</div>
                </div>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <?php
                            // Tentukan teks copyright secara dinamis
                            $copyright_text = '';
                            if (!empty($APP_SETTINGS['copyright_text'])) {
                                // Jika ada teks kustom di settings, gunakan itu
                                $copyright_text = htmlspecialchars($APP_SETTINGS['copyright_text']);
                            } else {
                                // Jika tidak, gunakan nama domain secara otomatis
                                $domain = $_SERVER['HTTP_HOST'];
                                $copyright_text = 'Copyright &copy; ' . htmlspecialchars(ucfirst($domain)) . ' ' . date('Y');
                            }
                            echo "<span>{$copyright_text}</span>";
                        ?>
                    </div>
                </div>
            </footer>
            </div>
        </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.2/js/bootstrap-multiselect.min.js"></script>

    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/startbootstrap-sb-admin-2/4.1.4/js/sb-admin-2.min.js"></script>
</body>
</html>