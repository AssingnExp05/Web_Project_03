<nav style="background: #374151; border-bottom: 1px solid #4b5563;">
    <div class="container">
        <ul style="display: flex; list-style: none; margin: 0; padding: 1rem 0;">
            <li style="margin-right: 2rem;">
                <a href="/admin/dashboard.php" style="color: #e5e7eb; text-decoration: none; padding: 0.5rem; border-radius: 0.25rem; transition: all 0.2s;">
                    📊 Dashboard
                </a>
            </li>
            <li style="margin-right: 2rem;">
                <a href="/admin/manageUsers.php" style="color: #e5e7eb; text-decoration: none; padding: 0.5rem; border-radius: 0.25rem; transition: all 0.2s;">
                    👥 Users
                </a>
            </li>
            <li style="margin-right: 2rem;">
                <a href="/admin/managePets.php" style="color: #e5e7eb; text-decoration: none; padding: 0.5rem; border-radius: 0.25rem; transition: all 0.2s;">
                    🐕 Pets
                </a>
            </li>
            <li style="margin-right: 2rem;">
                <a href="/admin/manageAdoptions.php" style="color: #e5e7eb; text-decoration: none; padding: 0.5rem; border-radius: 0.25rem; transition: all 0.2s;">
                    📋 Adoptions
                </a>
            </li>
            <li style="margin-right: 2rem;">
                <a href="/admin/manageVaccinations.php" style="color: #e5e7eb; text-decoration: none; padding: 0.5rem; border-radius: 0.25rem; transition: all 0.2s;">
                    💉 Vaccinations
                </a>
            </li>
            <li style="margin-right: 2rem;">
                <a href="/admin/reports.php" style="color: #e5e7eb; text-decoration: none; padding: 0.5rem; border-radius: 0.25rem; transition: all 0.2s;">
                    📈 Reports
                </a>
            </li>
        </ul>
    </div>
</nav>

<style>
nav a:hover {
    background: #4b5563 !important;
    color: white !important;
}
nav a.active {
    background: #3b82f6 !important;
    color: white !important;
}
</style>

<script>
// Highlight active nav item
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = window.location.pathname;
    const navLinks = document.querySelectorAll('nav a');
    
    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});
</script>