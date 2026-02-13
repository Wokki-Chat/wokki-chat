const overlay = document.querySelector('.hover-overlay');
const menus = document.querySelectorAll('.header-option.multiple');

menus.forEach(menu => {
    menu.addEventListener('mouseenter', () => {
        overlay.style.opacity = '1';
    });
    menu.addEventListener('mouseleave', () => {
        overlay.style.opacity = '0';
    });
    menu.addEventListener('focusin', () => {
        overlay.style.opacity = '1';
    });
    menu.addEventListener('focusout', () => {
        overlay.style.opacity = '0';
    });
});
