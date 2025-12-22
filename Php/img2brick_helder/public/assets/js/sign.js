// Sélection des éléments
const signInForm = document.getElementById('signInForm');
const signUpForm = document.getElementById('signUpForm');
const toSignUp = document.getElementById('toSignUp');
const toSignIn = document.getElementById('toSignIn');

// Clique sur "Sign up"
toSignUp.addEventListener('click', (e) => {
    e.preventDefault();
    signInForm.classList.remove('active');
    signUpForm.classList.add('active');
});

// Clique sur "Sign in"
toSignIn.addEventListener('click', (e) => {
    e.preventDefault();
    signUpForm.classList.remove('active');
    signInForm.classList.add('active');
});