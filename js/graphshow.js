function merge_indexes(indexes) {
    if (indexes.length >= 2) {
        let indexes_A = indexes[0];
        let indexes_B = indexes[1];

        let indexes_AB = Array.from(new Set(indexes_A.concat(indexes_B)));

        if (indexes.length == 2) {
            return indexes_AB;
        } else {
            indexes_N = [indexes_AB].concat(indexes.slice(2));
            return merge_indexes(indexes_N);
        }
    } else if (indexes.length == 1) {
        return indexes[0];
    } else {
        return [];
    }
}

function merge_datasets(datasets, value_name) {
    var datapoints = Array.from(Array(datasets.length), () => []);

    indexes = datasets.map(d => d.map(x => x['timestamp']));
    indexes = merge_indexes(indexes);

    for (let i = 0; i < indexes.length; i++) {
        for (let j = 0; j < datasets.length; j++) {
            value = NaN;
            for (let k = 0; k < datasets[j].length; k++) {
                if (datasets[j][k]['timestamp'] == indexes[i]) {
                    value = datasets[j][k][value_name];
                    break;
                }
            }
            datapoints[j].push(value);
        }
    }
    return {
        'indexes': indexes,
        'datapoints': datapoints
    };
}

async function getDatasets(StationIDs, type) {
    var requestOptions = {
        method: 'GET',
        redirect: 'follow'
    };

    let ids = StationIDs.map((x)=>`id[]=${x}`);
    let idsString = Array.from(ids).join('&');

    let response = await fetch(`/php/api/getData.php?${idsString}&type=${type}`, requestOptions);
    let datasets = await response.json()

    return Object.values(datasets);
}

// function timestamp2Label(timestamp) {
//     var options = {
//         'year': '2-digit',
//         'month': '2-digit',
//         'day': '2-digit',
//         'hour': '2-digit',
//         'minute': '2-digit'
//     };
//     date = new Date(timestamp * 1000);
//     return date.toLocaleString('it-IT', options);
// }

function toData(dataset, key, colors) {
    merged = merge_datasets(dataset.map(x => x['data']), key);
    // labels = merged['indexes'].map(x => (timestamp2Label(x)));
    labels = merged['indexes'].map((x)=>moment(x*1000));
    right_bound = moment(labels[labels.length-1]).endOf('day');
    left_bound = moment(labels[0]).startOf('day');

    // console.log(bound);

    const skipped = (ctx, value) => ctx.p0.skip || ctx.p1.skip ? value : undefined;

    datasets = [];
    for (var i = 0; i < merged['datapoints'].length; i++) {
        datasets.push({
            label: dataset[i]['name'],
            data: merged['datapoints'][i],
            borderColor: colors[i],
            backgroundColor: colors[i] + '4f',
            fill: false,
            cubicInterpolationMode: 'monotone',
            tension: 0.4,
            spanGaps: true,
            segment: {
                borderDash: ctx => skipped(ctx, [2, 2]),
            },
        });
    }
    return {
        labels: labels,
        datasets: datasets
    };
}

function genericConfig(data, title, metric) {
    return  {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            responsiveAnimationDuration: 0.2,
            plugins: {
                title: {
                    display: true,
                    text: title
                },
            },
            interaction: {
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true
                    },
                    type: 'time',
                    time: {
                        unit:'minute',
                        stepSize:30,
                        displayFormats: {
                            minute: 'DD MMM HH:mm'
                        }
                    },
                    suggestedMax: right_bound,
                    suggestedMin: left_bound,
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: metric
                    }
                }
            },
            animations: {
                radius: {
                    duration: 300,
                    easing: 'easeInOutElastic',
                    loop: (context) => context.active
                }
            },
        },
    };
}

async function ThermoChartConfig(series) {
    var dataset = await getDatasets(series.map(x => x.id), 'termo')
    var data = toData(dataset, ['temperature'][0], series.map(x => x.color));

    return genericConfig(data, 'Temperatura', '°C')
}

async function PluvioChartConfig(series, cumulative = false) {
    var dataset = await getDatasets(series.map(x => x.id), 'pluvio')
    var data = toData(dataset, ['level', 'cumulative'][(cumulative) ? 1 : 0], series.map(x => x.color));

    return genericConfig(data, 'Pioggia' + ((cumulative) ? ' cumulativa' : ''), 'mm')
}

const SERIES = [
    {
        id: 'TOS11000002', //Cecina
        color: '#ff6384',
    },
    {
        id: 'TOS11000513', //Quercianella
        color: '#36a2eb',
    },
    {
        id: 'TOS11000036', //Collesalvetti
        color: '#4bc0c0',
    },
    {
        id: 'TOS03002283', //San Vincenzo
        color: '#ffcf5e',
    },
    {
        id: 'TOS03002459', //Follonica
        color: '#ff9f40',
    },
    {
        id: 'TOS11000013', //Casotto del pescatore
        color: '#9966ff',
    },
    {
        id: 'TOS11000106', //Passo Radici
        color: '#c9cbcf',
    },
]

async function main() {
    t_promise = ThermoChartConfig(SERIES);          //temperatura
    p_promise = PluvioChartConfig(SERIES, false);   //temperatura
    pc_promise = PluvioChartConfig(SERIES, true);    //temperatura
    
    t_config = await t_promise;
    p_config = await p_promise;
    pc_config = await pc_promise;

    t_elem = document.getElementById('thermoChart');
    new Chart(t_elem.getContext('2d'), t_config);

    p_elem = document.getElementById('pluvioChart');
    new Chart(p_elem.getContext('2d'), p_config);

    pc_elem = document.getElementById('pluvioCumulativeChart');
    new Chart(pc_elem.getContext('2d'), pc_config);
}