            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
    (function() {
        const body = document.body;
        const html = document.documentElement;
        const toggleBtn = document.getElementById('sidebarToggle');
        const innerToggleBtn = document.getElementById('sidebarToggleInner');
        const closeBtn = document.getElementById('sidebarCloseBtn');
        const backdrop = document.getElementById('sidebarBackdrop');

        function isMobile() {
            return window.innerWidth <= 768;
        }

        function toggleSidebar() {
            if (isMobile()) {
                body.classList.toggle('sidebar-mobile-open');
            } else {
                const isCollapsed = body.classList.toggle('sidebar-collapsed');
                html.classList.toggle('sidebar-collapsed', isCollapsed);
                try {
                    localStorage.setItem('sales_sidebar_collapsed', isCollapsed ? 'true' : 'false');
                } catch(e) {}
            }
        }

        function closeMobileSidebar() {
            body.classList.remove('sidebar-mobile-open');
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }

        if (innerToggleBtn) {
            innerToggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSidebar();
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeMobileSidebar();
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function() {
                closeMobileSidebar();
            });
        }

        // Close on ESC key on mobile
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && body.classList.contains('sidebar-mobile-open')) {
                closeMobileSidebar();
            }
        });

        // Auto-sync on window resize
        window.addEventListener('resize', function() {
            if (!isMobile()) {
                body.classList.remove('sidebar-mobile-open');
                const savedState = localStorage.getItem('sales_sidebar_collapsed');
                if (savedState === 'true') {
                    body.classList.add('sidebar-collapsed');
                    html.classList.add('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-collapsed');
                    html.classList.remove('sidebar-collapsed');
                }
            }
        });

        // Initialize state on desktop
        if (!isMobile() && localStorage.getItem('sales_sidebar_collapsed') === 'true') {
            body.classList.add('sidebar-collapsed');
            html.classList.add('sidebar-collapsed');
        }
    })();
    </script>
</body>
</html>

