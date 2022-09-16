function merge_indexes(indexes) {
    if (indexes.length >= 2) {
        indexes_A = indexes[0];
        indexes_B = indexes[1];

        indexes_AB = Array.from(new Set(indexes_A.concat(indexes_B)));

        if (indexes.length > 2) {
            indexes_N = indexes_AB.concat(indexes.slice(2));
            // indexes_N = [indexes_AB, ...(indexes.slice(2))];
            return merge_indexes(indexes_N);
        } else {
            return indexes_AB;
        }
    } else if (indexes.length == 1) {
        return indexes[0];
    } else {
        return [];
    }
}

function merge_datasets(datasets, value_name) {
    var datapoints = [
        [],
        []
    ];

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

    promises = [];
    for (var i=0; i<StationIDs.length; i++) {
        promises.push(fetch(`/php/api/getData.php?id=${StationIDs[i]}&type=${type}`, requestOptions))
    }
    datasets = [];
    for (var i=0; i<promises.length; i++) {
        response = await promises[i];
        datasets.push(await response.json())
    }
    return datasets;
}

function timestamp2Label(timestamp) {
    var options = {
        'day': '2-digit',
        'month': 'short',
        'year': '2-digit',
        'hour': '2-digit',
        'minute': '2-digit'
    };
    date = new Date(timestamp * 1000);
    return date.toLocaleString('it-IT', options);
}

function toData(dataset, key, colors) {
    merged = merge_datasets(dataset.map(x => x['data']), key);
    labels = merged['indexes'].map(x => (timestamp2Label(x)));

    datasets = [];
    for (var i=0; i<merged['datapoints'].length; i++) {
        datasets.push({
            label: dataset[i]['name'],
            data: merged['datapoints'][i],
            borderColor: colors[i],
            fill: false,
            cubicInterpolationMode: 'monotone',
            tension: 0.4
        });
    }
    return {
        labels: labels,
        datasets: datasets
    };
}

async function main() {

    var DATASET = await getDatasets(['TOS11000002', 'TOS03002016'], 'termo')

    const data = toData(DATASET, 'temperature', ['#ff6384', '#36a2eb', '#4bc0c0']);
    console.log(data);

    const config = {
        type: 'line',
        yValueFormatString: "#0.## °C",
        data: data,
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Chart.js Line Chart - Cubic interpolation mode'
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
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: '°C'
                    },
                    suggestedMin: -10,
                    suggestedMax: 40
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

    const ctx = document.getElementById('myChart').getContext('2d');
    const myChart = new Chart(ctx, config);
}