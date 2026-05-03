    </div><!-- End page-content -->
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        var body = document.body;
        var overlay = document.getElementById('adminSidebarOverlay');
        var mqOpen = window.matchMedia('(max-width: 991.98px)');

        function setExpanded(open) {
            document.querySelectorAll('.admin-sidebar-toggle[aria-expanded]').forEach(function (btn) {
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        function openSidebar() {
            body.classList.add('admin-sidebar-open');
            setExpanded(true);
        }

        function closeSidebar() {
            body.classList.remove('admin-sidebar-open');
            setExpanded(false);
        }

        function toggleSidebar() {
            if (body.classList.contains('admin-sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        document.querySelectorAll('.admin-sidebar-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!mqOpen.matches) {
                    return;
                }
                toggleSidebar();
            });
        });

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        document.querySelectorAll('.sidebar a.sidebar-link').forEach(function (a) {
            a.addEventListener('click', function () {
                if (mqOpen.matches) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function () {
            if (!mqOpen.matches) {
                closeSidebar();
            }
        });
    })();
    </script>
</body>
</html>
