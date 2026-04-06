<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') {
        return;
    }

    const styles = getComputedStyle(document.documentElement);
    const primary = styles.getPropertyValue('--primary').trim() || '#0f766e';
    const secondary = styles.getPropertyValue('--secondary').trim() || '#f97316';
    const success = styles.getPropertyValue('--success').trim() || '#15803d';
    const warning = styles.getPropertyValue('--warning').trim() || '#d97706';
    const danger = styles.getPropertyValue('--danger').trim() || '#dc2626';
    const info = styles.getPropertyValue('--info').trim() || '#0284c7';
    const muted = styles.getPropertyValue('--text-muted').trim() || '#64748b';
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(20, 33, 61, 0.08)';
    const borderColor = isDark ? 'rgba(12, 20, 34, 0.9)' : '#ffffff';

    Chart.defaults.color = muted;
    Chart.defaults.font.family = '"Manrope", "Segoe UI", sans-serif';

    const attendancePulseCanvas = document.getElementById('attendancePulseChart');
    if (attendancePulseCanvas) {
        new Chart(attendancePulseCanvas, {
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Check-ins',
                        data: <?= json_encode($trendPresent) ?>,
                        backgroundColor: primary + 'CC',
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 34
                    },
                    {
                        type: 'line',
                        label: 'Late arrivals',
                        data: <?= json_encode($trendLate) ?>,
                        borderColor: warning,
                        backgroundColor: warning,
                        tension: 0.35,
                        yAxisID: 'y',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        type: 'line',
                        label: 'Avg hours',
                        data: <?= json_encode($trendHours) ?>,
                        borderColor: secondary,
                        backgroundColor: secondary,
                        tension: 0.35,
                        yAxisID: 'y1',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 18
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        title: {
                            display: true,
                            text: 'Check-ins'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'Average Hours'
                        }
                    }
                }
            }
        });
    }

    const departmentMixCanvas = document.getElementById('departmentMixChart');
    if (departmentMixCanvas) {
        new Chart(departmentMixCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    data: <?= json_encode($deptCounts) ?>,
                    backgroundColor: [primary, success, warning, info, secondary, danger, '#0f172a', '#14b8a6', '#f59e0b', '#ec4899'],
                    borderColor: borderColor,
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 18
                        }
                    }
                }
            }
        });
    }

    const leavePortfolioCanvas = document.getElementById('leavePortfolioChart');
    if (leavePortfolioCanvas) {
        new Chart(leavePortfolioCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($leaveLabels) ?>,
                datasets: [
                    {
                        label: 'Approved',
                        data: <?= json_encode($leaveApproved) ?>,
                        backgroundColor: success,
                        borderRadius: 10,
                        borderSkipped: false
                    },
                    {
                        label: 'Pending',
                        data: <?= json_encode($leavePending) ?>,
                        backgroundColor: warning,
                        borderRadius: 10,
                        borderSkipped: false
                    },
                    {
                        label: 'Rejected',
                        data: <?= json_encode($leaveRejected) ?>,
                        backgroundColor: danger,
                        borderRadius: 10,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 18
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        stacked: true,
                        grid: {
                            color: gridColor
                        }
                    },
                    y: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});
</script>
