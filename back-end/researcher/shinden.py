from aiohttp import ClientSession, TCPConnector, FormData
from asyncio import run, sleep, gather
from random  import randint
from json    import loads, dumps
from bs4     import BeautifulSoup, NavigableString
from re      import findall
from base64  import b64decode

shindenHeaders = {
    "accept-language": "en-US,en;q=0.9,pl;q=0.8",
    "user-agent"     : "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36"
}

class Shinden():
    aiohttpSession = None
    # base64 encoded user auth key default "_guest_:0,5,21000000,255,4174293644"
    # Structure "NICKNAME:USERID,5,DATE,3,UNKNOWN"
    token = "X2d1ZXN0XzowLDUsMjEwMDAwMDAsMjU1LDQxNzQyOTM2NDQ"
    url   = ""
    username = None
    password = None
    userId   = None
    
    def __init__(self, proxyUrl = "https://your-site.com/shinden.php"):
        self.url = proxyUrl
        self.aiohttpSession = ClientSession(
            connector=TCPConnector(limit=1)
        )

    async def close(self):
        await self.aiohttpSession.close()
    
    async def get(self, url, retry=1):
        r = None

        async with self.aiohttpSession.get(url + (f"&token={self.token}" if self.token is not None else "")) as r:
            r = loads(await r.text())

        if r             is None: return []
        if r['exitCode'] !=    0: return r

        if r['result'] == "" and retry > 0:
            self.refreshToken()
            return self.get(url, retry-1)

        return r['result']

    async def refreshToken(self):
        self.token = await self.get(f"{self.url}?method=login&username={self.username}&password={self.password}")
        return self.token

    async def login(self, username, password):
        self.username = username
        self.password = password
        self.token = await self.get(f"{self.url}?method=login&username={username}&password={password}")
        return self.token

    async def searchAnime(self, name, options = ""):
        return await self.get(f"{self.url}?method=search&title={name}" + ("&options=" + options if options != "" else ""))

    async def getAnimeEpisodes(self, animeId):
        return await self.get(f"{self.url}?method=getanimeepisodes&id={animeId}")

    async def getEpisodePlayers(self, episodeUrl):
        return await self.get(f"{self.url}?method=getepisodeplayers&url={episodeUrl}")

    async def getPlayer(self, id):
        return await self.get(f"{self.url}?method=getplayer&id={id}")
