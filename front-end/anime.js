var url = window.location.href;
var id = url.substring(url.search(/anime\//i)+6);
var episodes = document.getElementById("episodes");
var playerServers = document.getElementById("playerServers");
var playerFrame = document.getElementById("playerFrame");
var animeData = null;
 
function displayPlayer(id){
    if(e = document.getElementById("animeInfo")) {e.remove();playerFrame.hidden = false;}
    callAPIAS(`method=getplayer&id=${id}`).then(r => { playerFrame.src = r['result'][0]; });
}

function displayEpisode(url){
    callAPIAS(`method=getepisodeplayers&url=${url}`).then(r => {
        let currentList = document.querySelectorAll("tr");
        for(let i = 1; i < currentList.length; i++) currentList[i].remove();
        for(player of r['result']){
            playerServers.innerHTML += `<tr>
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
    for(let i = 0; i < eps; i++){
        console.log(i);
        episodes.innerHTML += `<div style="opacity: ${(eps-i-1)/eps}" class="episode">1</div>`;
    
    }

    document.getElementById("coverImg").src       = r['result']['coverArtUrl'];
    document.getElementById("descText").innerText = r['result']['description'];

    document.getElementById("title").innerText = r['result']['title'];
    document.getElementById("type" ).innerText = r['result']['type'];
    document.getElementById("eps"  ).innerText = r['result']['episodesCount'];
    document.getElementById("score").innerText = r['result']['malRating'];
}).then(r => {
callAPIAS(`method=getanimeepisodes&id=${id}`).then(r => {
    animeData = r['result'];
    episodes.innerHTML = "";
    for(let i = animeData.length - 1; i >= 0 ; i--){
        let ep = animeData[i];
        let node = document.createElement('div');
        
        node.classList.add("clickable");
        node.classList.add("episode");
        node.innerHTML = ep['number'] + " - " + ep['title'];
        node.id        = "eparid" + i;
        
        node.addEventListener('click', (e) => {
            displayEpisode(animeData[e.target.id.slice(6)]['url']);
        });

        episodes.appendChild(node);
    }


    
})})