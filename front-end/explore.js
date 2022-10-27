class Carousel {
    rightScroll = null;
    leftScroll  = null;
    container   = null;
    scrollPos   = 0;

    constructor(parent){
        this.rightScroll = document.createElement('a');
        this. leftScroll = document.createElement('a');
        this.  container = document.createElement('div');
        
        this.rightScroll.innerHTML = '<svg style="padding-top:125px;" fill="white" width="50px" viewBox="0 0 59.414 59.414" style="enable-background:new 0 0 59.414 59.414;" xml:space="preserve"><polygon points="15.561,0 14.146,1.414 42.439,29.707 14.146,58 15.561,59.414 45.268,29.707 "/>';
        this. leftScroll.innerHTML = '<svg style="padding-top:125px;" fill="white" width="50px" viewBox="0 0 59.414 59.414" style="enable-background:new 0 0 59.414 59.414;" xml:space="preserve"><polygon points="45.268,1.414 43.854,0 14.146,29.707 43.854,59.414 45.268,58 16.975,29.707 "/>';

        this.rightScroll.hidden = true;
        this. leftScroll.hidden = true;

        this.rightScroll.classList.add('arrow__btn', 'clickable');
        this. leftScroll.classList.add('arrow__btn', 'clickable');
        this.  container.classList.add('items');

        this.leftScroll .addEventListener("click", () => { Carousel.scroll(this, -this.container.clientWidth/1.2); });
        this.rightScroll.addEventListener("click", () => { Carousel.scroll(this,  this.container.clientWidth/1.2); });

        parent.appendChild(this. leftScroll);
        parent.appendChild(this.  container);
        parent.appendChild(this.rightScroll);
    }

    clear(){
        this.container.innerHTML = "";
    }

    addItem(item) {
        this.container.innerHTML += item;
        this.updateButtons();
    }

    addAnime(imageUrl, title, url){
        if (title.length > 20) title = title.substring(0, 20) + "...";
        this.addItem(`<a href="${url}"><div class="item"><img class="item-image" src="${imageUrl}"/><span class="item-title">${title}</span></div></a>`);
    }

    static scroll(carousel, delta, duration=500) {
        carousel.scrollPos += delta;
        carousel.updateButtons();

        if (carousel.container.scrollLeft === carousel.scrollPos) return;
        
        const cosParameter = (carousel.container.scrollLeft - carousel.scrollPos) / 2;
        let scrollCount = 0, oldTimestamp = null;
        
        function step (newTimestamp) {
            if (oldTimestamp !== null) {
                // if duration is 0 scrollCount will be Infinity
                scrollCount += Math.PI * (newTimestamp - oldTimestamp) / duration;
                if (scrollCount >= Math.PI) return carousel.container.scrollLeft = carousel.scrollPos;

                carousel.container.scrollLeft = cosParameter + carousel.scrollPos + cosParameter * Math.cos(scrollCount);
            }
            oldTimestamp = newTimestamp;
            window.requestAnimationFrame(step);
        }
        window.requestAnimationFrame(step);
    }

    isScrolledAllLeft() {
        return this.scrollPos <= 0;
    }

    isScrolledAllRight() {
        if (this.container.scrollWidth > this.container.offsetWidth) {
            return this.scrollPos + this.container.offsetWidth >= this.container.scrollWidth;
        }
        
        return true;
    }

    updateButtons(){
        this. leftScroll.hidden = this.isScrolledAllLeft ();
        this.rightScroll.hidden = this.isScrolledAllRight();
    }
}

class Content {
    parent = null;
    sections = {};

    constructor(parent){
        this.parent = parent;
    }

    clear(){
        for(let section of Object.keys(this.sections)){
            this.sections[section][1].remove();
            this.sections[section][2].remove();
            delete this.sections[section];
        }
    }

    addCarousel(name){
        let element = document.createElement("div");
        let title   = document.createElement("h1");

        title.innerText = name;
        
        element.style.position = "relative";
        element.style.width    = "100%";

        this.parent.appendChild(title  );
        this.parent.appendChild(element);

        let carousel = new Carousel(element);

        this.sections[name] = [carousel, element, title];
        
        return carousel;
    }
}

var home = document.getElementById("home");
var content = new Content(home);

function preloadContainer(){
    let c1 = content.addCarousel(" ");
    for(let i =0 ;i < c1.container.clientWidth / 200; i++) c1.addAnime("blank", "");
}
//preloadContainer();

function checkSite(){
    let url = window.location.href;

    if ((i = url.search(/search\?q\=/i)) > 0) return search(url.substring(i+9), false);
    
    mainPage();
}

var accountMenuDropdown = document.querySelector('#account-drop-down'); accountMenuDropdown.hidden = true;

document.querySelector('#logout').addEventListener('click', () => { signout(true) })
document.querySelector('#account').addEventListener('click', () => { accountMenuDropdown.hidden = !accountMenuDropdown.hidden; })

document.getElementById("searchInput").addEventListener('blur', (e) => {
    if(e.target.value != "") return;

    let sd = document.getElementById("search");

    e.target.style.borderWidth = "0px";

    e.target.style.display = "none";
});

function mainPage(){
    content.clear();
    var a = content.addCarousel("This season");

    fetch("https://rin.yukiteru.xyz/API.php?method=getAnime").then(r => r.text()).then(r => {
        r = JSON.parse(r)['result'];
        r.forEach(e => {
            a.addAnime(e['thumbnailUrl'], e['title'], '/anime/' + e['id']);
        });
    });

    var b = content.addCarousel("Prequels");
    fetch("https://rin.yukiteru.xyz/API.php?method=getAnime&offset=20").then(r => r.text()).then(r => {
        r = JSON.parse(r)['result'];
        console.log(r);
        r.forEach(element => { b.addAnime(element['thumbnailUrl'], element['title'], '/anime/' + element['id']); });
    });

    var c = content.addCarousel("xD");
    fetch("https://rin.yukiteru.xyz/API.php?method=getAnime&offset=100").then(r => r.text()).then(r => {
        r = JSON.parse(r)['result'];
        console.log(r);
        r.forEach(element => { c.addAnime(element['thumbnailUrl'], element['title'], '/anime/' + element['id']); });
    });
}

window.onpopstate = function(e){
    document.title = "Explore";
    mainPage();
};

function search(title, newsite = true){
    home.innerHTML = "";

    if(newsite) window.history.pushState   ({"pageTitle":`Search - ${title}`}, "", `//rin.yukiteru.xyz/search?q=${title}`);
    else        window.history.replaceState({"pageTitle":`Search - ${title}`}, "", `//rin.yukiteru.xyz/search?q=${title}`);
    document.title = "Search";

    content.clear();
    content.addCarousel("Results");
    content.addCarousel("Results MAL");


    callAPIAS(`method=getnameproposalsdb&hint=${title}`).then(r => {        
        let results = content.sections['Results'];

        results[0].clear();

        r['result'].forEach(e => {
            results[0].addAnime(e['thumbnailUrl'], e['title'], '/anime/' + e['id']);
        });
    }).then(() => {
    callAPIAS(`method=getnameproposalsonline&hint=${title}`).then(r => {
        let results = content.sections['Results MAL'];

        results[0].clear();

        r['result'].forEach(e => {
            results[0].addAnime(e['thumbnailUrl'], e['title'], '/anime/' + e['id']);
        });
    })})
}
checkSite();

document.getElementById("searchInput").addEventListener('keypress', (e) => {
    if(e.key != "Enter") return;
    search(e.target.value);
});

document.getElementById("search").addEventListener('click', (e) => {
    let si = document.getElementById("searchInput");

    si.style.display = "inline";

    e.target.style.borderWidth = "1px";
    document.getElementById("search").classList.remove("clickable");

    si.focus();
});