document.addEventListener("DOMContentLoaded", function() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    // Số lượng hạt đom đóm sao vừa phải để không bị rối mắt
    const particleCount = 20; 

    for (let i = 0; i < particleCount; i++) {
        let particle = document.createElement('div');
        particle.classList.add('floating-particle');

        // Phân bố vị trí ngang ngẫu nhiên khắp bề ngang sidebar
        let posX = Math.random() * header.offsetWidth;
        // Kích thước hạt nhỏ li ti
        let size = Math.random() * 2 + 1.5; 
        
        // Tốc độ bay chậm rãi, lững lờ (từ 12 đến 20 giây)
        let duration = Math.random() * 8 + 12; 
        
        // Thời gian chờ xuất hiện lệch nhau
        let delay = Math.random() * 8; 

        // Màu sắc hạt sao
        let colors = ['#ffffff', '#00f5a0', '#ff1e56'];
        let chosenColor = colors[Math.floor(Math.random() * colors.length)];

        particle.style.left = posX + 'px';
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.backgroundColor = chosenColor;
        // Giảm độ tỏa sáng (box-shadow) xuống một chút để không bị chói và nhòe
        particle.style.boxShadow = `0 0 5px ${chosenColor}`;
        particle.style.animationDuration = duration + 's';
        particle.style.animationDelay = delay + 's';

        header.appendChild(particle);
    }
});
document.addEventListener("DOMContentLoaded", function () {
    const btnToggle = document.getElementById('btn-su-kien-toggle');
    const submenu = document.getElementById('submenu-su-kien');

    if (btnToggle && submenu) {
        btnToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            // Toggle bật/tắt class active cho submenu
            submenu.classList.toggle('show');
        });

        // Tự động đóng menu con khi nhấn ra ngoài
        document.addEventListener('click', function (e) {
            if (!btnToggle.contains(e.target) && !submenu.contains(e.target)) {
                submenu.classList.remove('show');
            }
        });
    }
});