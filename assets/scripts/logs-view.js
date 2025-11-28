(function() {
    // Фильтрация логов
    const filterButtons = document.querySelectorAll('.log-filter-btn');
    const logEntries = document.querySelectorAll('.log-entry');
    const limitSelect = document.getElementById('logLimitSelect');
    const logCountInfo = document.querySelector('.log-count-info');
    
    // Обработка фильтрации
    if (filterButtons.length > 0 && logEntries.length > 0) {
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const level = this.getAttribute('data-level');
                
                // Обновляем активную кнопку
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Фильтруем записи
                let visibleCount = 0;
                logEntries.forEach(entry => {
                    const entryLevel = entry.getAttribute('data-level');
                    if (level === 'all' || entryLevel === level) {
                        entry.removeAttribute('data-filtered');
                        entry.style.display = '';
                        visibleCount++;
                    } else {
                        entry.setAttribute('data-filtered', 'true');
                        entry.style.display = 'none';
                    }
                });
                
                // Обновляем счетчик
                if (logCountInfo) {
                    logCountInfo.textContent = 'Показано ' + visibleCount + ' записів';
                }
            });
        });
    }
    
    // Обработка изменения лимита
    if (limitSelect) {
        limitSelect.addEventListener('change', function() {
            const limit = this.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('limit', limit);
            window.location.href = currentUrl.toString();
        });
    }
})();

