window.chart = null;
window.currentType = 'regs';
window.currentMode = '30';

window.handleToggle = function(btn, prop, val) {
    window[prop] = val;
    const group = btn.closest('[data-toggle]');
    if (group) {
        group.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    }
    btn.classList.add('active');
    window.updateChart();
};

function buildGradient(ctx) {
    const g = ctx.createLinearGradient(0, 0, 0, 180);
    g.addColorStop(0, 'rgba(255,27,115,0.28)');
    g.addColorStop(1, 'rgba(255,27,115,0)');
    return g;
}

function get30DayLabels() {
    const labels = [];
    for (let i = 29; i >= 0; i--) {
        const d = new Date();
        d.setDate(d.getDate() - i);
        labels.push(`${d.getDate()}.${d.getMonth() + 1}.`);
    }
    return labels;
}

const chartScales = {
    x: {
        display: true,
        grid: { display: false },
        ticks: {
            color: 'rgba(255,255,255,0.28)',
            font: { size: 10 },
            maxTicksLimit: 6,
            maxRotation: 0,
        },
        border: { display: false }
    },
    y: {
        display: true,
        beginAtZero: true,
        grid: {
            color: 'rgba(255,255,255,0.055)',
        },
        ticks: {
            color: 'rgba(255,255,255,0.38)',
            font: { size: 10 },
            maxTicksLimit: 4,
            precision: 0,
        },
        border: { display: false }
    }
};

function initChart() {
    const canvas = document.getElementById('statsChart');
    if (!canvas || window.chart) return;

    fetch('/api/stats.php')
        .then(res => res.json())
        .then(data => {
            window.statsData = data;
            const ctx = canvas.getContext('2d');

            window.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: get30DayLabels(),
                    datasets: [{
                        data: data.regs30,
                        borderColor: '#FF1B73',
                        backgroundColor: buildGradient(ctx),
                        fill: true,
                        tension: 0.4,
                        pointRadius: 2.5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#FF1B73',
                        pointBorderColor: 'transparent',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(18,0,30,0.93)',
                            titleColor: 'rgba(255,255,255,0.5)',
                            bodyColor: '#FF1B73',
                            padding: 10,
                            cornerRadius: 8,
                            titleFont: { size: 10 },
                            bodyFont: { size: 13, weight: '600' },
                            callbacks: {
                                title: (items) => items[0]?.label ?? '',
                                label: (c) => ' ' + c.raw + (window.currentType === 'regs' ? ' registrací' : ' zobrazení')
                            }
                        }
                    },
                    scales: chartScales
                }
            });
        })
        .catch(console.error);
}

// Poll for canvas — social page loads dynamically via fetch
const _chartBoot = setInterval(() => {
    if (document.getElementById('statsChart')) {
        clearInterval(_chartBoot);
        initChart();
    }
}, 100);

window.updateChart = function() {
    const title = document.getElementById('graph-title');
    if (title) {
        const typeText = window.currentType === 'regs' ? 'Registrace' : 'Zobrazení';
        const modeText = window.currentMode === '30' ? '30 dní' : 'Celkem';
        title.innerText = `${typeText} (${modeText})`;
    }

    if (!window.chart || !window.statsData) return;

    const data = window.statsData;
    let dataset, labels;

    if (window.currentType === 'regs') {
        dataset = window.currentMode === '30' ? data.regs30 : data.regsAll;
    } else {
        dataset = window.currentMode === '30' ? data.views30 : data.viewsAll;
    }

    if (!dataset || !dataset.length) return;

    labels = window.currentMode === '30' ? get30DayLabels() : dataset.map((_, i) => `den ${i + 1}`);

    window.chart.data.labels = labels;
    window.chart.data.datasets[0].data = dataset;
    window.chart.update();
};

window.setType = function(type) {
    window.currentType = type;
    window.updateChart();
};
