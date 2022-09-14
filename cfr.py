from datetime import datetime
import aiohttp
from aiohttp import web
import json
from enum import Enum

PORT = 8547

class CFR:
    class Type(Enum):
        HYDRO       = 'idro'
        PLUVIO      = 'pluvio'
        THERMO      = 'termo'
        ANEMO       = 'anemo'
        HYGRO       = 'igro'

    @staticmethod
    def __parseText(text:'str') -> 'list[str]':
        lines = [line.strip(' \r') for line in text.split('\n')]
        start_line = lines.index('//<![CDATA[')
        end_line = lines.index('//]]>')
        return [line for line in lines[start_line+1:end_line-1] if line.startswith('VALUES')]

    @staticmethod
    def __parseLine(line:'str') -> 'tuple[int, datetime, str, str]':
        id_str, date_str, value, direction = [value.strip('"') for value in line.split('Array(',1)[1].split(');',1)[0].split(',',4)]
        return int(id_str), datetime.strptime(date_str, '%d/%m/%Y %H.%M'), value, direction

    @staticmethod
    async def getData(type:'CFR.Type', station_id:'str'):
        parser = {
            CFR.Type.HYDRO: CFR.__parseHydroLine,
            # CFR.Type.PLUVIO: CFR.__parsePluvioLine,
            # CFR.Type.THERMO: CFR.__parseThermoLine,
            CFR.Type.ANEMO: CFR.__parseAnemoLine,
            # CFR.Type.HYGRO: CFR.__parseHygroLine,
        }[type]

        async with aiohttp.ClientSession() as session:
            async with session.post("https://cfr.toscana.it/monitoraggio/dettaglio.php", params={'id': station_id, 'type':type.value}) as resp:
                lines = CFR.__parseText(await resp.text())
                return [(parser(line)) for line in lines] 

    @staticmethod
    def __parseHydroLine(line:'str') -> 'dict':
        _, date, level_str, _ = CFR.__parseLine(line)
        return {'timestamp':int(date.timestamp()), 'level':float(level_str)}

    @staticmethod
    def __parseAnemoLine(line:'str') -> 'dict':
        _, date, value_str, direction_str = CFR.__parseLine(line)
        speed, burst = value_str.split('/',1)
        return {'timestamp':int(date.timestamp()), 'speed':float(speed), 'burst':float(burst), 'direction':float(direction_str)}


app = web.Application()
routes = web.RouteTableDef()

@routes.get('/getData/{type}/{station_id}')
async def get_handler(request):
    identifier = request.match_info['station_id']
    type = CFR.Type(request.match_info['type'])

    js = await CFR.getData(type, identifier)
    return web.Response(text=json.dumps(js, indent=2), content_type="application/json")
    
app.add_routes(routes)

web.run_app(app, port=PORT)
