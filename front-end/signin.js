'use strict';

function onSubmit(){
	let usernameField = document.getElementById('login__username');
	let passwordField = document.getElementById('login__password');

	// remove 'field_error' tag making fields borders red if wrong
	usernameField.classList.remove('field_error');
	passwordField.classList.remove('field_error');

	let username = usernameField.value;
	let password = passwordField.value;

	// Hash password for seciurity
	password = sjcl.hash.sha256.hash  (password);
    password = sjcl.codec.hex.fromBits(password);

	signin(username, password).then((response)=>{
		switch( (response === false) ? -1 : response['exitCode'] ){
			case 0:
				if (document.getElementById('keepSignedin').checked)
					localStorage.setItem('preventSignout', true);

				window.location.href = './explore';
				break;
			case 1:
				passwordField.classList.add('field_error');
				error('Unable to sign in. Please check your password and try again.', 3000);
				break;
			case 2:
				usernameField.classList.add('field_error');
				error('User with such email/username does not exists.', 3000);
				break;
			default:
				error('Something went wrong :/ try again later.', 3000);
				break;
		}
	})

	return false;
}

document.getElementById('signin_form').onsubmit = onSubmit;
