// HAMBURGER-NAV

const bodyWrap = document.querySelector('body');
const navWrap = document.querySelector('.nav-wrap');
const hamburgerWrap = document.querySelector('.hamburger-wrap');
const navItems = document.querySelectorAll('.nav-item')

const navOpen = () =>{
    navWrap.classList.toggle('open');
    hamburgerWrap.classList.toggle('open');
    setTimeout(() => {
        bodyWrap.classList.toggle('not-scroll');
    }, 1000);
};

hamburgerWrap.addEventListener('click', navOpen);

navItems.forEach(element => {
    element.addEventListener('click', navOpen);
});