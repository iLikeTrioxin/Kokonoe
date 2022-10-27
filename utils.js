const apiURL = '/API.php?';

'use strict';

// return json
async function postAsync(url, data=''){
    return fetch(url, {
        'method' : 'POST',
        'body'   : data,
        'headers': {
            'accept': '*/*',
            'Content-Type': 'application/x-www-form-urlencoded',
            'cookie': `PHPSESSID=${localStorage['SID']}`
        }
    });
}

async function callAPIAS(data, retry=true){
	return postAsync( apiURL, data ).then( res => res.json() ).then( (response) => {
		if((response['exitCode'] == 7) && (retry == true)) return signin().then( (res) => {
			if(res === false) return false;

			return callAPIAS(data, false);
		});

		return response;
	});
}

function error(msg, ms){
	let errorMsg = document.getElementById('errorMessage');

	if (errorMsg == null){
		errorMsg = document.createElement('div');

		errorMsg.id = 'errorMessage';

		errorMsg.classList.add('error');
		errorMsg.classList.add('hide' );

		document.body.appendChild(errorMsg);
	}

	errorMsg.innerHTML = msg;
	errorMsg.classList.remove('hide');

	setTimeout(function(a, b){a.classList.add(b)}, ms, errorMsg, 'hide');
}

function post(url, data){
	let xhr = new window.XMLHttpRequest;
	xhr.open('POST', url, false);

	//Send the proper header information along with the request
	xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	//xhr.onreadystatechange = callback;
	//xhr.onreadystatechange = function() {
	//	//Call a function when the state changes.
	//	if(xhr.readyState == 4 && xhr.status == 200) {
	//		alert(xhr.responseText);
	//	}
	//}

	xhr.send(data);

	if(xhr.readyState != 4 && xhr.status != 200)
		return xhr.status;

	return xhr.responseText;
}

// TODO: make signin and signup use callApi function instead of just post

async function signup(username, email, password){
	return callAPIAS(`method=signup&username=${username}&email=${email}&password=${password}`, false).then( (response) => {
		if(response['exitCode'] == 0){
			localStorage.setItem('username', username);
			localStorage.setItem('password', password);
			localStorage.setItem('email'   , email   );

			localStorage.setItem('SID', response['result']['SID']);
		}

		return response;
	})
}

async function signin(username=null, password=null){
	if(username == null || password == null){
		if(!localStorage['username'] || !localStorage['password']) return new Promise( (r) => r(false) );

		username = localStorage['username'];
		password = localStorage['password'];
	}

	return callAPIAS(`method=signin&username=${username}&password=${password}`, false).then( (response) => {
		if(response['exitCode'] == 0){
			localStorage.setItem('username', username);
			localStorage.setItem('password', password);

			localStorage.setItem('SID', response['result']['SID']);
		}

		return response;
	})
}

async function signout(moveToSignin=true){
    localStorage.removeItem('username'      );
    localStorage.removeItem('password'      );
    localStorage.removeItem('preventSignout');

    return callAPIAS('method=signout', false).then( () => {
        moveToSignin ? (window.location.href = './signin') : null;
    });
}


var __lastScrollX__ = 0;
var __lastScrollY__ = 0;

var __noScroll__ = () => { window.scrollTo(__lastScrollX__, __lastScrollY__); };

function lockScroll(){
    __lastScrollX__ = window.scrollX;
    __lastScrollY__ = window.scrollY;
    window.addEventListener('scroll', __noScroll__);
}

function unlockScroll(){
    window.removeEventListener('scroll', __noScroll__);
}

function lockScreen(){
    let div = document.createElement('div');
    div.id = '__lockScreen__';
    div.style = 'z-index:5;position:fixed;top:0;left:0;width:100vw;height:100vh;background-color:black;opacity:75%;';
    document.querySelector('html').appendChild(div);
}

function unlockScreen(){
    let lock = document.getElementById('__lockScreen__');

    if(lock != undefined) lock.remove();
}

function askUser(title, question, yesCallback, noCallback){
    lockScreen();
    lockScroll();

    let questionWindow = document.createElement('div');
    questionWindow.id = '__currentQuestion__';
    questionWindow.style = 'z-index:10;position:fixed;text-align:center;width:65vw;height:50vh;top:50%;left:50%;transform:translate(-50%,-50%);background-color:#222;';

    let windowTitle = document.createElement('h1');
    windowTitle.style = 'margin-top:5%;font-size:2rem;position:relative;color:rgb(255,255,255);font-weight:bold;text-transform:uppercase;';
    windowTitle.innerHTML = title;

    let windowQuestion = document.createElement('h2');
    windowQuestion.style = 'margin-top:10%;font-size:1rem;margin-bottom:10%;position:relative;color:rgb(255,255,255);font-weight:bold;text-transform:uppercase;';
    windowQuestion.innerHTML = question;


    let yesButton = document.createElement('a');
    yesButton.style     = 'cursor:pointer;margin:5%;padding:2% 5%;border:2px solid white;border-radius:5px;position:relative;color:rgb(255,255,255);font-weight:bold;text-transform:uppercase;';
    yesButton.innerHTML = 'Yes';

    yesButton.addEventListener('click', ()  => { unlockScreen(); unlockScroll(); document.getElementById('__currentQuestion__').remove(); });
    yesButton.addEventListener('click', yesCallback);

    let noButton = document.createElement('a');
    noButton.style     = 'cursor:pointer;margin:5%;padding:2% 5%;border:2px solid white;border-radius:5px;position:relative;color:rgb(255,255,255);font-weight:bold;text-transform:uppercase;';
    noButton.innerHTML = 'No';

    noButton.addEventListener('click', ()  => { unlockScreen(); unlockScroll(); document.getElementById('__currentQuestion__').remove(); });
    noButton.addEventListener('click', noCallback);

    questionWindow.append(windowTitle, windowQuestion, yesButton, noButton);

    document.querySelector('html').appendChild(questionWindow);
}
