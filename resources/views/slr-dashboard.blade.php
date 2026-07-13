<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic SLR - Premium Analytics</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        brand: { 50: '#f0fdfa', 100: '#ccfbf1', 400: '#2dd4bf', 500: '#14b8a6', 600: '#0d9488', 900: '#134e4a' }
                    }
                }
            }
        }
    </script>
    <style>
        /* Modern Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Premium Range Slider */
        input[type=range] { -webkit-appearance: none; background: transparent; width: 100%; }
        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none; height: 16px; width: 16px; border-radius: 50%;
            background: #ffffff; border: 2px solid #0f172a; cursor: pointer; margin-top: -6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.15s ease;
        }
        input[type=range]::-webkit-slider-thumb:hover { transform: scale(1.15); box-shadow: 0 4px 6px rgba(0,0,0,0.15); }
        input[type=range]::-webkit-slider-runnable-track { width: 100%; height: 4px; cursor: pointer; background: #e2e8f0; border-radius: 4px; }
        input[type=range]:focus { outline: none; }
        
        /* Row Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .row-animate { animation: fadeIn 0.4s ease forwards; opacity: 0; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased selection:bg-brand-100 selection:text-brand-900">

    <!-- Header Section -->
    <div class="bg-slate-950 pb-44 pt-16 relative overflow-hidden">
        <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] rounded-full bg-brand-500/10 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] rounded-full bg-blue-600/10 blur-[120px] pointer-events-none"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-slate-300 text-[11px] font-bold tracking-widest uppercase mb-6 backdrop-blur-md">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-400 animate-pulse"></span>
                SLR Decision Support System
            </div>
            <h1 class="text-4xl md:text-6xl font-extrabold text-white tracking-tight">
                Literature <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-400 to-blue-400">Analytics</span>
            </h1>
            <p class="mt-5 max-w-2xl mx-auto text-slate-400 text-lg font-medium">
                Pencarian literatur cerdas dengan pembobotan multikriteria TOPSIS.
            </p>
        </div>
    </div>

    <!-- Container Utama -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-28 pb-12 relative z-20">
        <div class="flex flex-col xl:flex-row gap-6 items-start">
            
            <!-- PANEL KIRI: Sidebar Kontrol -->
            <div class="w-full xl:w-[340px] shrink-0 bg-white p-6 rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200/60 xl:sticky xl:top-6">
                
                <!-- Keyword Input -->
                <div class="mb-8">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 italic">1. Identifikasi Topik</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-brand-500 transition-colors">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" id="searchKeyword" placeholder="Gunakan spasi (Contoh: smart city iot algorithm)..." 
                               class="block w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl bg-slate-50/50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 text-sm font-semibold transition-all">
                    </div>
                </div>

                <!-- Weights Control -->
                <div class="space-y-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest italic mb-2">2. Kriteria, Arah &amp; Prioritas (1-5)</label>

                    <script>
                        const items = [
                            { id: 'c1', name: 'Scopus Quality' },
                            { id: 'c2', name: 'SINTA Quality' },
                            { id: 'c3', name: 'Citations' },
                            { id: 'c4', name: 'Recency (Year)' },
                            { id: 'c5', name: 'Author H-Index' },
                            { id: 'c6', name: 'Author SINTA Score' }
                        ];
                        const activeBtnClass = 'flex-1 text-[9px] font-black uppercase tracking-wider py-1 rounded-md border transition-colors bg-slate-900 text-white border-slate-900';
                        const inactiveBtnClass = 'flex-1 text-[9px] font-black uppercase tracking-wider py-1 rounded-md border transition-colors bg-white text-slate-400 border-slate-200';
                        items.forEach(c => {
                            document.write(`
                                <div class="group" data-criterion="${c.id}">
                                    <div class="flex justify-between items-center mb-1.5">
                                        <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                            <input type="checkbox" id="enable_${c.id}" checked onchange="toggleCriterion('${c.id}')" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                            ${c.name}
                                        </label>
                                        <span id="val_${c.id}" class="text-[10px] font-bold bg-slate-900 text-white py-0.5 px-2 rounded">3</span>
                                    </div>
                                    <input type="range" id="w_${c.id}" min="1" max="5" value="3" oninput="document.getElementById('val_${c.id}').innerText = this.value">
                                    <input type="hidden" id="dir_${c.id}" value="benefit">
                                    <div class="flex items-center gap-2 mt-2">
                                        <button type="button" id="btn_${c.id}_benefit" onclick="setDirection('${c.id}','benefit')" class="${activeBtnClass}">Benefit</button>
                                        <button type="button" id="btn_${c.id}_cost" onclick="setDirection('${c.id}','cost')" class="${inactiveBtnClass}">Cost</button>
                                    </div>
                                </div>
                            `);
                        });
                    </script>
                </div>

                <button onclick="fetchRecommendations()" class="mt-8 w-full flex justify-center items-center gap-2 bg-slate-900 text-white font-bold py-4 px-4 rounded-xl shadow-lg hover:bg-slate-800 transition-all transform active:scale-95 text-xs uppercase tracking-widest">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Kalkulasi Matriks
                </button>
            </div>

            <!-- PANEL KANAN: Tabel Hasil -->
            <div class="w-full bg-white p-0 rounded-2xl shadow-xl shadow-slate-200/40 border border-slate-200/60 min-h-[600px] flex flex-col relative overflow-hidden">
                
                <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/30">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900 tracking-tight uppercase italic">Publication Ranking</h2>
                        <p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest mt-1" id="resultMeta">Result Engine</p>
                    </div>
                </div>
                
                <!-- Loading -->
                <div id="loading" class="hidden absolute inset-0 bg-white/90 backdrop-blur-sm z-30 flex flex-col items-center justify-center pt-20">
                    <div class="w-10 h-10 border-[3px] border-slate-100 border-t-brand-500 rounded-full animate-spin"></div>
                    <p class="text-slate-900 font-extrabold text-[10px] mt-4 uppercase tracking-[0.2em] animate-pulse">Computing Euclidean Matrix</p>
                </div>

                <!-- Empty State (Awaiting Params) -->
                <div id="emptyState" class="flex-1 flex flex-col items-center justify-center p-12 text-center group">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-500 border border-slate-100 shadow-inner">
                        <svg class="w-10 h-10 text-slate-300 group-hover:text-brand-400 transition-colors duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <h3 class="text-slate-900 font-bold text-base mb-2 italic uppercase tracking-wider">Awaiting Input Parameters</h3>
                    <p class="text-slate-400 text-xs max-w-sm font-medium leading-relaxed uppercase tracking-widest">
                        Masukkan kata kunci pencarian dan atur prioritas kriteria untuk memulai analisis matriks keputusan.
                    </p>
                    <div class="mt-8 flex gap-2">
                        <span class="w-1.5 h-1.5 bg-slate-200 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                        <span class="w-1.5 h-1.5 bg-slate-200 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                        <span class="w-1.5 h-1.5 bg-slate-200 rounded-full animate-bounce" style="animation-delay: 0.3s"></span>
                    </div>
                </div>

                <!-- Error -->
                <div id="errorMessage" class="hidden p-8 z-20">
                    <div class="bg-red-50 text-red-700 px-5 py-4 rounded-xl border border-red-100 text-xs font-bold flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span id="errorText"></span>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto hidden" id="resultsContainer">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50/50">
                            <tr>
                                <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest w-16">Rank</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Article Identity</th>
                                <th class="px-6 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Index</th>
                                <th class="px-6 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Cites</th>
                                <th class="px-6 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">V-Score (TOPSIS)</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="bg-white divide-y divide-slate-100">
                            <!-- JS Injection -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripting -->
    <script>
        function toggleCriterion(id) {
            const enabled = document.getElementById(`enable_${id}`).checked;
            const container = document.querySelector(`[data-criterion="${id}"]`);
            container.querySelectorAll('input[type=range], button').forEach(el => el.disabled = !enabled);
            container.style.opacity = enabled ? '1' : '0.4';
        }

        function setDirection(id, direction) {
            document.getElementById(`dir_${id}`).value = direction;
            const activeBtnClass = 'flex-1 text-[9px] font-black uppercase tracking-wider py-1 rounded-md border transition-colors bg-slate-900 text-white border-slate-900';
            const inactiveBtnClass = 'flex-1 text-[9px] font-black uppercase tracking-wider py-1 rounded-md border transition-colors bg-white text-slate-400 border-slate-200';
            document.getElementById(`btn_${id}_benefit`).className = direction === 'benefit' ? activeBtnClass : inactiveBtnClass;
            document.getElementById(`btn_${id}_cost`).className = direction === 'cost' ? activeBtnClass : inactiveBtnClass;
        }

        async function fetchRecommendations() {
            const keywordInput = document.getElementById('searchKeyword').value;
            const loading = document.getElementById('loading');
            const emptyState = document.getElementById('emptyState');
            const resultsContainer = document.getElementById('resultsContainer');
            const errorMessage = document.getElementById('errorMessage');
            const tableBody = document.getElementById('tableBody');
            const resultMeta = document.getElementById('resultMeta');

            // 1. Validasi Keyword Wajib
            if (!keywordInput.trim()) {
                errorMessage.classList.remove('hidden');
                document.getElementById('errorText').innerText = "Mohon masukkan kata kunci (Keyword) pencarian sebelum mengeksekusi matriks.";
                document.getElementById('searchKeyword').focus();
                return;
            }

            loading.classList.remove('hidden');
            errorMessage.classList.add('hidden');
            emptyState.classList.add('hidden'); // Sembunyikan empty state saat kalkulasi

            const allCriteria = ['c1', 'c2', 'c3', 'c4', 'c5', 'c6'];
            const criteria = allCriteria.filter(id => document.getElementById(`enable_${id}`).checked);

            if (criteria.length === 0) {
                loading.classList.add('hidden');
                errorMessage.classList.remove('hidden');
                document.getElementById('errorText').innerText = "Minimal satu kriteria harus aktif.";
                return;
            }

            const weights = {};
            const directions = {};
            criteria.forEach(id => {
                weights[id] = parseInt(document.getElementById(`w_${id}`).value);
                directions[id] = document.getElementById(`dir_${id}`).value;
            });

            const payload = {
                keyword: keywordInput,
                criteria: criteria,
                weights: weights,
                directions: directions
            };

            try {
                const response = await fetch('/api/topsis/recommendation', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.message || 'Error Server.');

                resultMeta.innerHTML = `<span class="text-brand-600 tracking-[0.05em] font-black italic uppercase">
                    <svg class="inline-block w-4 h-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    ${data.total_found} Dokumen Ditemukan &bull; Menampilkan ${data.displayed_count} Ranking Teratas
                </span>`;

                let html = '';
                data.recommendations.forEach((item, index) => {
                    const scopus = item.metrics.scopus_quartile ? `<span class="bg-slate-900 text-white text-[9px] font-black px-2 py-0.5 rounded tracking-tighter uppercase mr-1 shadow-sm">${item.metrics.scopus_quartile}</span>` : '';
                    const sinta = item.metrics.sinta_accreditation ? `<span class="border border-slate-200 text-slate-600 text-[9px] font-black px-2 py-0.5 rounded tracking-tighter uppercase shadow-sm">${item.metrics.sinta_accreditation}</span>` : '';
                    const rankStyle = index < 3 ? 'bg-brand-500 text-white shadow-lg shadow-brand-200' : 'bg-slate-100 text-slate-500 border border-slate-200';
                    const doiLink = item.doi ? `https://doi.org/${item.doi}` : '#';

                    html += `
                        <tr class="hover:bg-slate-50/50 transition-all group row-animate" style="animation-delay: ${index * 0.04}s">
                            <td class="px-6 py-6 align-top">
                                <div class="flex items-center justify-center w-8 h-8 rounded-lg font-black text-xs mx-auto ${rankStyle}">${index + 1}</div>
                            </td>
                            <td class="px-6 py-6 align-top">
                                <a href="${doiLink}" target="_blank" class="block text-sm font-bold text-slate-800 leading-snug mb-3 group-hover:text-brand-600 transition-colors">
                                    ${item.title}
                                    <svg class="inline-block w-3.5 h-3.5 ml-1 opacity-20 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                                </a>
                                <div class="flex items-center gap-3 text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em]">
                                    <span class="flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg> ${item.metrics.year}</span>
                                    <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
                                    <span class="text-slate-600">H-IDX: <b class="text-slate-900">${item.metrics.author_scopus_hindex}</b></span>
                                    <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
                                    <span class="text-slate-600">SINTA: <b class="text-slate-900">${item.metrics.author_sinta_score}</b></span>
                                </div>
                            </td>
                            <td class="px-6 py-6 align-top pt-7">${scopus}${sinta}</td>
                            <td class="px-6 py-6 align-top pt-7 text-center font-bold text-xs text-slate-700 tabular-nums">${item.metrics.citations}</td>
                            <td class="px-6 py-6 align-top pt-6 text-right">
                                <span class="inline-block bg-slate-50 border border-slate-200 text-slate-900 px-3 py-1.5 rounded-lg font-black text-xs shadow-sm tabular-nums tracking-tighter">
                                    ${item.topsis_score.toFixed(4)}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                tableBody.innerHTML = html;
                resultsContainer.classList.remove('hidden');

            } catch (error) {
                document.getElementById('errorText').innerText = error.message;
                errorMessage.classList.remove('hidden');
                resultsContainer.classList.add('hidden');
                emptyState.classList.remove('hidden');
                resultMeta.innerText = "FAILED";
            } finally {
                loading.classList.add('hidden');
            }
        }
    </script>
</body>
</html>