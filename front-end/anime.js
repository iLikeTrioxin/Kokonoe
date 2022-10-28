var url = window.location.href;
var id = url.substring(url.search(/anime\//i)+6);

var episodes      = get("#episodes");
var playerServers = get("#playerServers");
var playerFrame   = get("#playerFrame");
var animeData     = null;

function displayPlayer(id){
    let animeInfo = get("#animeInfo");
    
    if(animeInfo)
        animeInfo.remove();
    
    playerFrame.hidden = true;
    get("#loading").classList.remove("hide")
    
    get("#loading").hidden = false;
    callAPIAS(`method=getplayer&id=${id}`).then(r => {
        playerFrame.src = r['result'][0];

        get("#loading").classList.add("hide");
        playerFrame.hidden = false;
    });
}

function displayEpisode(url){
    callAPIAS(`method=getepisodeplayers&url=${url}`).then(r => {
        let currentList = getAll("tr");
        for(let i = 1; i < currentList.length; i++) currentList[i].remove();
        for(player of r['result']){
            playerServers.innerHTML += `<tr class="alternate">
            <td>${player['player']}</td>
            <td>${player['lang_audio']}</td>
            <td>${player['lang_subs']}</td>
            <td>${player['max_res']}</td>
            <td><button class="clickable" onclick="displayPlayer(${player['online_id']})">ogladaj</button></td>
            </tr>`;
        }
    });
}

callAPIAS(`method=getanimebyid&id=${id}`).then(r => {
    let eps = r['result']['episodesCount'];
    console.log(r);
    
    for(let i = 0; i < eps; i++)
        episodes.innerHTML += `<div style="opacity: ${(eps-i-1)/eps}" class="episode alternate"></div>`;


    get("#mal").classList.remove("hide");
    get("#mal").href = "//myanimelist.net/anime/" + r['result']['malId'];
    
    get("#coverImg").src       = r['result']['coverArtUrl'];
    get("#descText").innerText = r['result']['description'];

    get("#title").innerText = r['result']['title'];
    get("#type" ).innerText = r['result']['type'];
    get("#eps"  ).innerText = r['result']['episodesCount'];
    get("#score").innerText = r['result']['malRating'];
}).then(r => {
callAPIAS(`method=getanimeepisodes&id=${id}`).then(r => {
    animeData = r['result']['episodes'];
    episodes.innerHTML = "";
    for(let i = animeData.length - 1; i >= 0 ; i--){
        let ep = animeData[i];
        let node = document.createElement('div');
 
        get("#shinden").classList.remove("hide");
        get("#shinden").href = "//shinden.pl/series/" + r['result']['shindenId'];

        node.classList.add("clickable");
        node.classList.add("alternate");
        node.classList.add("episode");
        node.innerHTML = ep['number'] + " - " + ep['title'];
        node.id        = "eparid" + i;
        
        node.addEventListener('click', (e) => {
            displayEpisode(animeData[e.target.id.slice(6)]['url']);
        });

        episodes.appendChild(node);
    }


    
})})