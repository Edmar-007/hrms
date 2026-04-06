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

    const growthHiringCanvas = document.getElementById('growthHiringChart');
    if (growthHiringCanvas) {
        new Chart(growthHiringCanvas, {
            data: {
                labels: <?= json_encode($growthLabels) ?>,
                datasets: [
                    {
                        type: 'bar',
                        label: 'New hires',
                        data: <?= json_encode($growthHires) ?>,
                        backgroundColor: primary + 'CC',
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 30
                    },
                    {
                        type: 'line',
                        label: 'Cumulative headcount',
                        data: <?= json_encode($growthHeadcount) ?>,
                        borderColor: secondary,
                        backgroundColor: secondary,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
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
                            text: 'New Hires'
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
                            text: 'Headcount'
                        }
                    }
                }
            }
        });
    }

    const tenureMixCanvas = document.getElementById('tenureMixChart');
    if (tenureMixCanvas) {
        new Chart(tenureMixCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($tenureLabels) ?>,
                datasets: [{
                    data: <?= json_encode($tenureCounts) ?>,
                    backgroundColor: [primary, success, warning, info, secondary],
                    borderColor: borderColor,
                    borderWidth: 3,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
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

    const departmentFootprintCanvas = document.getElementById('departmentFootprintChart');
    if (departmentFootprintCanvas) {
        new Chart(departmentFootprintCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($deptLabels) ?>,
                datasets: [{
                    label: 'Active employees',
                    data: <?= json_encode($deptCounts) ?>,
                    backgroundColor: [primary, success, warning, info, secondary, danger, '#0f172a', '#14b8a6', '#f59e0b', '#ec4899'],
                    borderRadius: 12,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    const attendanceMomentumCanvas = document.getElementById('attendanceMomentumChart');
    if (attendanceMomentumCanvas) {
        new Chart(attendanceMomentumCanvas, {
            data: {
                labels: <?= json_encode($attendanceLabels) ?>,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Employees seen',
                        data: <?= json_encode($attendancePresent) ?>,
                        backgroundColor: info + 'CC',
                        borderRadius: 12,
                        borderSkipped: false,
                        maxBarThickness: 34
                    },
                    {
                        type: 'line',
                        label: 'Avg hours',
                        data: <?= json_encode($attendanceHours) ?>,
                        borderColor: success,
                        backgroundColor: success,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y1'
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
                            text: 'Employees'
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

    const leaveRhythmCanvas = document.getElementById('leaveRhythmChart');
    if (leaveRhythmCanvas) {
        new Chart(leaveRhythmCanvas, {
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
                        stacked: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        stacked: true,
                        grid: {
                            color: gridColor
                        }
                    }
                }
            }
        });
    }
});
</script>
