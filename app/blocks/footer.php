</main>

<footer class="site-footer">
    <p>PhotoBooth — capture, decorate, and share your photos</p>

    <p class="footer-links">
        <a href="index.php?page=gallery">Gallery</a>
        <?php if (!empty($user)): ?>
            <a href="index.php?page=editor">Editor</a>
            <a href="index.php?page=my_images">My images</a>
            <a href="index.php?page=settings">Settings</a>
        <?php else: ?>
            <a href="index.php?page=login">Login</a>
            <a href="index.php?page=register">Register</a>
        <?php endif; ?>
    </p>
</footer>

</body>
</html>
