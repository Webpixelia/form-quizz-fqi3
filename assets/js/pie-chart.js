function createPieChart(canvasId, data, labels) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
  
    new Chart(ctx.getContext('2d'), {
      type: 'pie',
      data: {
        labels: labels,
        datasets: [{
          data: [data.successRate, 100 - data.successRate],
          backgroundColor: ['#4caf50', '#ccc'],
        }],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false,
          },
        },
      },
    });
  }
  
  function createLineChart(canvasId, data) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !data || !data.periods || !data.success_rates) return;
    const successRateLabel = fqi3PieChartData.lineChartLabels.successRateLabel;
  
    new Chart(ctx.getContext('2d'), {
      type: 'line',
      data: {
        labels: data.periods,
        datasets: [{
          label: successRateLabel,
          data: data.success_rates,
          borderColor: '#4caf50',
          fill: false,
        }],
      },
      options: {
        responsive: true,
      },
    });
  }
  
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof fqi3PieChartData === 'undefined') return;
  
    fqi3PieChartData.levels.forEach((level) => {
      const levelData = fqi3PieChartData.data[level];
      const labels = fqi3PieChartData.labels;
      createPieChart(`pieChart-${level}`, levelData, labels);
    });
  
    document.querySelectorAll('canvas[id^="monthly-"]').forEach((monthlyCanvas) => {
      const level = monthlyCanvas.id.replace('monthly-', '');
      const monthlyData = JSON.parse(monthlyCanvas.getAttribute('data-stats'));
      createLineChart(`monthly-${level}`, monthlyData);
    });
  
    document.querySelectorAll('canvas[id^="weekly-"]').forEach((weeklyCanvas) => {
      const level = weeklyCanvas.id.replace('weekly-', '');
      const weeklyData = JSON.parse(weeklyCanvas.getAttribute('data-stats'));
      createLineChart(`weekly-${level}`, weeklyData);
    });
  });