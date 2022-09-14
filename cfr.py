from datetime import datetime
import aiohttp
from aiohttp import web
import json
from enum import Enum

PORT = 8547

class CFR:
    class Type(Enum):
        HYDRO   = 'idro'
        PLUVIO  = 'pluvio'
        THERMO  = 'termo'
        ANEMO   = 'anemo'
        HYGRO   = 'igro'

    class TimeWindow(Enum):
        DAILY   = 1
        MONTHLY = 30

    def __init__(self, type:'CFR.Type', station_id:'str', time_window:'TimeWindow'= TimeWindow.DAILY) -> None:
        self.type = type
        self.id = station_id
        self.window = time_window
        self.parser = {
            self.Type.HYDRO: self.__parseHydroLine,
            self.Type.PLUVIO: self.__parsePluvioLine,
            self.Type.THERMO: self.__parseThermoLine,
            self.Type.ANEMO: self.__parseAnemoLine,
            self.Type.HYGRO: self.__parseHygroLine,
        }[self.type]

    def __parseText(self, text:'str') -> 'list[str]':
        lines = [line.strip(' \r') for line in text.split('\n')]
        start_line = lines.index('//<![CDATA[')
        end_line = lines.index('//]]>')
        return [line for line in lines[start_line+1:end_line-1] if line.startswith('VALUES')]

    def __parseLine(self, line:'str') -> 'tuple[int, datetime, str, str]':
        id_str, date_str, value, direction = [value.strip(' "') for value in line.split('Array(',1)[1].split(');',1)[0].split(',',4)]
        strp_format = '%d/%m/%Y %H.%M' if self.window == self.TimeWindow.DAILY else '%d/%m/%Y'
        return int(id_str), datetime.strptime(date_str, strp_format), value, direction

    async def getData(self):
        params = {
            'id':   self.id, 
            'type': self.type.value
        }

        if self.window == self.TimeWindow.MONTHLY:
            params['type'] += '_men'

        async with aiohttp.ClientSession() as session:
            async with session.post("https://cfr.toscana.it/monitoraggio/dettaglio.php", params=params) as resp:
                lines = self.__parseText(await resp.text())
                return [(self.parser(line)) for line in lines] 

    def __parseHydroLine(self, line:'str') -> 'dict':
        _, date, value_str, _ = self.__parseLine(line)
        return {'timestamp':int(date.timestamp()), 'level':float(value_str)}

    def __parsePluvioLine(self, line:'str') -> 'dict':
        _, date, value_str, cumulative_str = self.__parseLine(line)
        return {'timestamp':int(date.timestamp()), 'level':float(value_str), 'cumulative':float(cumulative_str) if len(cumulative_str)>0 else None}

    def __parseThermoLine(self, line:'str') -> 'dict':
        _, date, value_str, _ = self.__parseLine(line)
        return {'timestamp':int(date.timestamp()), 'temperature':float(value_str)}

    def __parseAnemoLine(self, line:'str') -> 'dict':
        _, date, value_str, direction_str = self.__parseLine(line)
        speed, burst = value_str.split('/',1)
        return {'timestamp':int(date.timestamp()), 'speed':float(speed), 'burst':float(burst), 'direction':float(direction_str)}

    def __parseHygroLine(self, line:'str') -> 'dict':
        _, date, value_str, _ = self.__parseLine(line)
        return {'timestamp':int(date.timestamp()), 'humidity':float(value_str)}
        
app = web.Application()
routes = web.RouteTableDef()

@routes.get('/{type}/{station_id}')
async def get_handler(request):
    identifier = str(request.match_info['station_id'])

    type = str(request.match_info['type'] )
    time_window = CFR.TimeWindow.DAILY
    if type.endswith('_men'):
        time_window = CFR.TimeWindow.MONTHLY
        type = type[:-4]
    type = CFR.Type(type)

    js = await CFR(type, identifier, time_window).getData()
    return web.Response(text=json.dumps(js, indent=2), content_type="application/json")

app.add_routes(routes)

web.run_app(app, port=PORT)
