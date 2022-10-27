import asyncio
import sys

sys.path.append(r"../..")

from json import dumps, loads
from sanic import Sanic
from sanic.response import json, html, text

from mal import AnimeSearch, Anime
from shinden import Shinden
from credentials import shindenPassword, shindenLogin

app = Sanic(__name__)

@app.route("/research", methods=['GET', 'POST'])
async def research(request):
    title = request.args.get("title")
    
    print(f"[ ](research) Requested for {title}")
    
    try:
        search = AnimeSearch(title)
    except:
        print(f"[!](research) Request for {title} failed.")
        return json({"code": 3, "response": "Error"})

    an = []
    for s in search.results:
        an.append({
            "mal_id": s.mal_id,
            "mal_url": s.url,
            "title": s.title,
            "thumbnailUrl": s.image_url,
            "coverArtUrl": s.image_url,
            "description": s.synopsis,
            "type": s.type,
            "episodesCount": s.episodes,
            "malRating": s.score
        })
    
    return json(an)

shinden = None
async def initShinden(retry:bool = True):
    global shinden
    print("[ ](initShinden) creating session")
    
    if shinden is not None:
        print("[*](initShinden) destroying previous session")
        await shinden.close()

    shinden = Shinden("https://fileblackhole.000webhostapp.com/API.php")

    token = await shinden.login(shindenLogin, shindenPassword)

    if len(token) != 64 and retry:
        print("[!](initShinden) resulting token not valid. retrying...")
        return initShinden(retry=False)
    
    print("[*](initShinden) Setting token to " + token)
    return token

@app.route("/search", methods=['GET', 'POST'])
async def search(request):
    global shinden

    if shinden is None: await initShinden()

    title   = request.args.get("title")
    options = request.args.get("options")

    print(f"[ ](search) requested for '{title}'")
    result = await shinden.searchAnime(title, options)

    if len(result) == 0:
        print(f"[!](search) can't find '{title}'")
        return json(result)

    cr = 0
    for pr in result:
        if pr['title'] == title: cr = result.index(pr)

    print(result[0])
    print(result[cr])
    tmp = result[0 ]
    result[0 ] = result[cr]
    result[cr] = tmp

    return json(result)

@app.route("/getepisodes", methods=['GET', 'POST'])
async def getEpisodes(request):
    global shinden

    if shinden is None: await initShinden()

    url = request.args.get("url")

    print(f"[ ](getepisodes) requested for '{url}'")
    episodes = await shinden.getAnimeEpisodes(url)

    return json(episodes)

@app.route("/getepisodeplayers", methods=['GET', 'POST'])
async def getEpisodePlayers(request):
    global shinden

    if shinden is None: await initShinden()

    url = request.args.get("url")

    players = await shinden.getEpisodePlayers(url)

    return json(players)

@app.route("/getplayer", methods=['GET', 'POST'])
async def getplayer(request):
    global shinden

    if shinden is None: await initShinden()

    id = request.args.get("id")

    print(f"[ ](getplayer) requested for '{id}'")

    return json([await shinden.getPlayer(id)])


@app.route("/researchDeep", methods=['GET', 'POST'])
async def researchDeep(request):
    global shinden

    if shinden is None: await initShinden()

    title = request.args.get("title")

    print(f"[ ](Deep research) requested for '{title}'")
    result = await shinden.searchAnime(title)

    for pr in result:
        if pr['title'] != title: del result[result.index(pr)]

    if len(result) == 0:
        print(f"[!](Deep research) can't find '{title}'")
        return json(result)

    episodes = await shinden.getAnimeEpisodes(result[0]['url'])

    if len(episodes) > 13:
        print(f"[*](Deep research) requested '{title}' which has more than 13 eps. Scraping only 2 eps.")
        episodes = episodes[-2:]
    
    for i in range(len(episodes)):
        episodes[i]['players'] = await shinden.getEpisodePlayers(episodes[i]['url'])

    return json(episodes)


if __name__ == "__main__":
    settings = {
        "researcherIp": "0.0.0.0",
        "researcherPort": 6002
    }

    with open("../../settings.json", "r") as f:
        settings = loads(f.read())

    app.run(
        host=settings['researcherIp'],
        port=settings['researcherPort']
    )
