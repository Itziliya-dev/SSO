function floatAnimation() {
    const logo = document.querySelectorAll('.logo-circle');
    logo.forEach(l => {
        const time = Date.now() / 1000;
        const y = Math.sin(time) * 8;
        const x = Math.cos(time * 0.7) * 3;
        l.style.transform = `translate(${x}px, ${y}px)`;
    });
    requestAnimationFrame(floatAnimation);
}


function preloadImages() {
    return new Promise((resolve) => {
        const images = [
            'assets/images/background.jpg',
            'assets/images/logo.png'
        ];
        
        let loaded = 0;
        images.forEach(src => {
            const img = new Image();
            img.src = src;
            img.onload = () => {
                loaded++;
                if (loaded === images.length) resolve();
            };
            img.onerror = () => {
                loaded++;
                if (loaded === images.length) resolve();
            };
        });
    });
}


async function initLoading() {
    try {
        
        document.querySelector('.loading-content').classList.add('show');
        
        
        await preloadImages();
        
        
        setTimeout(() => {
            document.querySelector('.loading-overlay').style.opacity = '0';
            setTimeout(() => {
                document.querySelector('.loading-overlay').style.display = 'none';
                document.querySelector('.login-container').style.opacity = '1';
                floatAnimation();
            }, 1000);
        }, 2000);
    } catch (error) {
        console.error('Error in loading:', error);
        
        document.querySelector('.loading-overlay').style.display = 'none';
        document.querySelector('.login-container').style.opacity = '1';
    }
}


window.addEventListener('load', initLoading);