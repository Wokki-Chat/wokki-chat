const images = [
    { id: 'bg-super-low', src: '/assets/images/login-bg-super-low.png', width: 853 },
    { id: 'bg-low', src: '/assets/images/login-bg-low.png', width: 1365 },
    { id: 'bg-normal', src: '/assets/images/login-bg-normal.png', width: 1920 },
    { id: 'bg-full', src: '/assets/images/login-bg.png', width: 3840 }
];
function getBestBg() {
    const w = window.innerWidth;
    return images.find(img => img.width >= w) || images[images.length - 1];
}
async function loadBg() {
    const bestBg = getBestBg();
    const img = document.getElementById(bestBg.id);
    if (!img.src) img.src = bestBg.src;
    await new Promise(resolve => img.onload = resolve);
    img.classList.add('visible');
}
loadBg();