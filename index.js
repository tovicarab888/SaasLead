/**
 * INDEX.JS - TAUFIKMARIE.COM ULTIMATE DASHBOARD
 * Version: 2.1.0 - FIXED: Hapus duplikasi kode + Chart single instance
 * FULL CODE - 100% JAVASCRIPT MURNI
 */

(function() {
    'use strict';
    
    console.log('Index.js loaded - Version 2.1.0');
    
    let chartInstance = null;
    
    // Initialize chart
    function initChart() {
        const canvas = document.getElementById('trendChart');
        if (!canvas) {
            console.log('Canvas element not found!');
            return;
        }

        // Hancurkan chart yang sudah ada jika ada
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        const ctx = canvas.getContext('2d');

        try {
            if (typeof Chart === 'undefined') {
                console.log('Chart.js not loaded yet');
                return;
            }

            // Ambil data dari global variable (diset oleh PHP)
            const labels = window.chartLabels || [];
            const data = window.chartData || [];

            if (labels.length === 0 || data.length === 0) {
                console.log('No chart data available');
                return;
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Leads',
                        data: data,
                        borderColor: '#D64F3C',
                        backgroundColor: 'rgba(214, 79, 60, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        pointBackgroundColor: '#D64F3C',
                        pointBorderColor: 'white',
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(27,74,60,0.9)',
                            titleColor: 'white',
                            bodyColor: 'rgba(255,255,255,0.9)',
                            borderColor: '#D64F3C',
                            borderWidth: 2,
                            padding: 12
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                stepSize: 1,
                                callback: function(value) {
                                    if (Math.floor(value) === value) {
                                        return value;
                                    }
                                }
                            }
                        }
                    }
                }
            });
            console.log('Chart initialized successfully');
        } catch (error) {
            console.error('Error initializing chart:', error);
        }
    }

    // Export functions ke global scope
    window.Index = {
        initChart: initChart
    };

    // Auto initialize ketika DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Tunggu Chart.js load
            if (typeof Chart !== 'undefined') {
                setTimeout(initChart, 200);
            } else {
                // Cek setiap 500ms
                const checkInterval = setInterval(function() {
                    if (typeof Chart !== 'undefined') {
                        clearInterval(checkInterval);
                        initChart();
                    }
                }, 500);
                
                // Timeout setelah 5 detik
                setTimeout(function() {
                    clearInterval(checkInterval);
                }, 5000);
            }
        });
    } else {
        // DOM already loaded
        if (typeof Chart !== 'undefined') {
            setTimeout(initChart, 200);
        }
    }
})();