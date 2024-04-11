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

formOpenButtons.forEach(button => {
  button.addEventListener('click', (event) => {
    event.preventDefault();
    const formKey = event.target.dataset.form;
    console.log(event.target);
    openContactForm(formKey);
  });
});

formCloseButton.addEventListener('click', closeContactForm);

const formData = {
  header: { id: '6824ff4', title: 'Header contact form' },
  product: { id: 'f4dc341', title: 'Product contact form' },
  footer: { id: '23d3b4e', title: 'Footer contact form_copy' },
};

function createContactFormHTML(formId, formTitle) {
  return `<?php echo do_shortcode( '[contact-form-7 id="${formId}" title="${formTitle}"]' ); ?>`;
}

function openContactForm(formKey) {
  const formId = formData[formKey].id;
  const formTitle = formData[formKey].title;
  const contactFormHTML = createContactFormHTML(formId, formTitle);
  contactFormBlock.querySelector('.contact-form').innerHTML = contactFormHTML;
  contactFormBlock.classList.add('form-open');
  setTimeout(() => {
    bodyWrap.classList.add('not-scroll');
}, 1000);
}

function closeContactForm() {
    const contactForm = contactFormBlock.querySelector('.contact-form');
    contactFormBlock.classList.remove('form-open');
    setTimeout(() => {
      bodyWrap.classList.remove('not-scroll');
      contactForm.innerHTML = '';
    }, 1000);
}