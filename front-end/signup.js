'use strict';

function onSubmit(event){
	let usernameField = document.getElementById('login__username');
	let    emailField = document.getElementById('login__email'   );
	let passwordFiled = document.getElementById('login__password');

	// Remove 'field_error' tag making fields borders red if wrong
	usernameField.classList.remove('field_error');
   	   emailField.classList.remove('field_error');

	let username = usernameField.value;
	let email    =    emailField.value;
	let password = passwordFiled.value;

	// Hash password for seciurity
	password = sjcl.hash.sha256.hash  (password);
    password = sjcl.codec.hex.fromBits(password);
    
	signup(username, email, password).then( (res) => {
		switch( (res === false) ? -1 : res['exitCode'] ){
			case 0:
				window.location.href = './explore';
                break;
			case 5:
				usernameField.classList.add('field_error');
				error('Sorry, user with that username already exists.', 3000);
				break;
			case 6:
				emailField.classList.add('field_error');
				error('Sorry, user with that email already exists.', 3000);
				break;
			case 4:
				emailField.classList.add('field_error');
				error('Sorry, that email is considered invalid.', 3000);
				break;
			default:
				error('Sorry, something went wrong. Try again later. :/', 3000);
				break;
		}
	});

	return false;
}

document.getElementById('signup_form').onsubmit = onSubmit;
