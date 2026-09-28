            <footer class="admin-footer" style="text-align:center; padding:12px; font-size:0.8rem; opacity:0.75;">
                <?php
                    if (function_exists('renderAuthorCreditLine')) {
                        echo renderAuthorCreditLine();
                    } elseif (function_exists('renderAuthorCredit')) {
                        echo renderAuthorCredit();
                    } else {
                        $an = defined('AUTHOR_NAME') ? AUTHOR_NAME : 'xxxx';
                        $au = defined('AUTHOR_GITHUB_URL') ? AUTHOR_GITHUB_URL : 'https://google.es';
                        echo '<a href="' . htmlspecialchars($au, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . htmlspecialchars($an, ENT_QUOTES, 'UTF-8') . '</a>';
                    }
                ?>
            </footer>
        </main>
    </div>
</body>
</html>
