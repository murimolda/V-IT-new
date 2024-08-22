// HAMBURGER-NAV

const bodyWrap = document.querySelector('body');
const navWrap = document.querySelector('.nav-wrap');
const hamburgerWrap = document.querySelector('.hamburger-wrap');
const navItems = document.querySelectorAll('.nav-item');

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

// FORM-OPEN

const contactFormBlock = document.querySelector('.contact-form-block');
const formOpenButtons = document.querySelectorAll('.form-open-button');
const formCloseButton = document.querySelector('.form-close');
const contactForms = document.querySelectorAll('.contact-form');

formOpenButtons.forEach(button => {
  button.addEventListener('click', (event) => {
    event.preventDefault();
    const formKey = event.target.dataset.form;
    openContactForm(formKey);
  });
});

formCloseButton.addEventListener('click', closeContactForm);

function openContactForm(formKey) {
  contactForms.forEach(form => {
    const dataForm = form.getAttribute('data-form');
    if (dataForm === formKey) {
      form.classList.add('form-active');
    } else {
      form.classList.remove('form-active');
    }
  });
  contactFormBlock.classList.add('form-open');
  setTimeout(() => {
    bodyWrap.classList.add('not-scroll');
  }, 1000);
}

function closeContactForm() {
  contactFormBlock.classList.remove('form-open');
  setTimeout(() => {
    bodyWrap.classList.remove('not-scroll');
}, 1000);
}

