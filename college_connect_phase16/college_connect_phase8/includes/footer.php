<?php $footerInAdmin = (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin'); $footerRoot = $footerInAdmin ? '../' : ''; ?>
</main>
<footer class="site-footer" id="campus-updates"><div class="footer-inner">
<a class="brand footer-brand" href="<?=$footerRoot?>index.php"><span class=" " aria-hidden="true"><img src="<?=$footerRoot?>assets/aitam.png" alt="AITAM SLACK" width="60" height="60" ></span><span >AITAM<span class="brand-light"> SLACK</span></span></a>
<p>A community where every question can find a helpful answer.</p>
<div class="footer-links"><a href="<?=$footerRoot?>index.php">Home</a><a href="<?=$footerRoot?>questions.php">Questions</a><a href="<?=$footerRoot?>college_updates.php">College updates</a><a href="<?=$footerRoot?>index.php#categories">Topics</a><a href="<?=$footerRoot?>privacy.php">Privacy</a><a href="<?=$footerRoot?>terms.php">Terms</a><?php if(current_user()):?><a href="<?=$footerRoot?>profile.php">My profile</a><?php endif;?></div>
</div><div class="footer-bottom"><span>© <?=date('Y')?> AITAM SLACK</span>

<div class="developer-section">
    <span class="developed-label">Developed By</span>

    <div class="developer-dropdown">
        <span class="dropdown-title">
           © CSM dep
            
        </span>

        <div class="dropdown-menu">
            <a href="#">P Yajneswara Rao</a>
            <a href="#">P PurnaChandra Rao</a>
            <a href="#">K Lokesh</a>
            <a href="#">Eswar Sir(SAC)</a>
             <a href="#">Dr Chandrasekhar(HOD)</a>
        </div>
    </div>
</div>
<style>
.developer-section {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    height: 40px;
    font-family: Arial, sans-serif;
    font-size: 12px;
}

.developed-label {
    color: var(--footer-text);
}

.developer-dropdown {
    position: relative;
    cursor: pointer;
}

.dropdown-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 28px;
    min-width: 115px;
    padding: 8px 10px;
    color: var(--footer-text);
    background: transparent;
    transition: color 0.3s ease;
    margin-left: -15px;
}

.dropdown-title:hover {
    color: var(--teal-highlight);
}

.arrow {
    transition: transform 0.3s ease;
}

.developer-dropdown:hover .arrow {
    transform: rotate(180deg);
}

.dropdown-menu {
    position: absolute;
    bottom: 100%;
    left: 0;
    min-width: 175px;
    padding: 6px;
    background: var(--dropdown-background);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: var(--shadow-overlay);
    opacity: 0;
    visibility: hidden;
    transform: translateY(8px);
    transition: opacity 0.25s ease,
    transform 0.25s ease,
    visibility 0.25s ease;
    z-index: 1000;
}

.developer-dropdown:hover .dropdown-menu,
.developer-dropdown:focus-within .dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-menu a {
    display: block;
    padding: 10px 12px;
    color: var(--body-text);
    text-decoration: none;
    border-radius: 5px;
    transition: background 0.25s ease,
    padding-left 0.25s ease;
}

.dropdown-menu a:hover {
    background: var(--hover-background);
    padding-left: 17px;
    color: var(--interactive-accent);
}
</style>
</span><span>Built for a more connected campus.</span></div></footer>
<script src="<?=$footerRoot?>assets/app.js"></script></body></html>
