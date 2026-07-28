(function() {
    if (typeof wpSchedulerSettings === 'undefined') {
        console.error('WP Scheduler Settings not found.');
        return;
    }

    const restUrl = wpSchedulerSettings.restUrl;
    const nonce = wpSchedulerSettings.nonce;

    let services = [];
    let activeService = null;
    let currentDate = new Date();
    let currentAvailability = null;
    let selectedDateStr = null;
    let selectedSlot = null;

    // Elements
    const views = {
        calendar: document.getElementById('calendar-view'),
        week: document.getElementById('week-view'),
        form: document.getElementById('form-view'),
        success: document.getElementById('success-view')
    };

    const steps = {
        1: document.getElementById('step1'),
        2: document.getElementById('step2'),
        3: document.getElementById('step3')
    };

    const progressBar = document.getElementById('progress');
    const serviceTitle = document.getElementById('service-title');
    const serviceSubtitle = document.getElementById('service-subtitle');

    async function init() {
        try {
            const response = await fetch(restUrl + '/services');
            if (!response.ok) throw new Error('Failed to load services');
            services = await response.json();
            
            if (services.length > 0) {
                activeService = services[0];
                serviceTitle.textContent = 'Agendar reunión';
                serviceSubtitle.textContent = activeService.name;
                renderCalendar();
            } else {
                serviceTitle.textContent = 'No hay servicios';
                serviceSubtitle.textContent = 'Actualmente no hay servicios disponibles.';
            }
        } catch (error) {
            console.error('Error init:', error);
            serviceTitle.textContent = 'Error';
            serviceSubtitle.textContent = 'No se pudieron cargar los servicios.';
        }

        document.getElementById('prev-month-btn').addEventListener('click', prevMonth);
        document.getElementById('next-month-btn').addEventListener('click', nextMonth);
        document.getElementById('back-to-calendar-btn').addEventListener('click', () => setView('calendar'));
        document.getElementById('back-to-week-btn').addEventListener('click', () => setView('week'));
        document.getElementById('confirm-btn').addEventListener('click', confirmBooking);
        document.getElementById('book-another-btn').addEventListener('click', () => location.reload());
    }

    function setView(viewName) {
        Object.values(views).forEach(v => {
            if (v) v.classList.add('hidden');
        });
        if (views[viewName]) views[viewName].classList.remove('hidden');

        // Update progress & steps
        let progress = 33;
        steps[1].classList.remove('active', 'completed');
        steps[2].classList.remove('active', 'completed');
        steps[3].classList.remove('active', 'completed');

        if (viewName === 'calendar') {
            steps[1].classList.add('active');
        } else if (viewName === 'week') {
            progress = 66;
            steps[1].classList.add('completed');
            steps[2].classList.add('active');
        } else if (viewName === 'form') {
            progress = 100;
            steps[1].classList.add('completed');
            steps[2].classList.add('completed');
            steps[3].classList.add('active');
        } else if (viewName === 'success') {
            progress = 100;
            steps[1].classList.add('completed');
            steps[2].classList.add('completed');
            steps[3].classList.add('completed');
        }

        if (progressBar) progressBar.style.width = progress + '%';
    }

    async function fetchAvailability(year, month) {
        if (!activeService) return;
        try {
            const url = new URL(restUrl + '/availability');
            url.searchParams.append('service_id', activeService.id);
            url.searchParams.append('year', year);
            url.searchParams.append('month', month);

            const response = await fetch(url.toString());
            if (!response.ok) throw new Error('Failed to load availability');
            const data = await response.json();
            currentAvailability = data.days || {};
            renderCalendarGrid();
        } catch (error) {
            console.error('Error fetching availability:', error);
            currentAvailability = {};
            renderCalendarGrid();
        }
    }

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth() + 1; // 1-12
        
        const monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        document.getElementById('month-year').textContent = `${monthNames[month-1]} de ${year}`;
        
        const grid = document.getElementById('calendar-grid');
        grid.replaceChildren(); // Safe way to clear

        ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sab'].forEach(day => {
            const label = document.createElement('div');
            label.className = 'day-label';
            label.textContent = day;
            grid.appendChild(label);
        });

        // Add loading text temporarily
        const loading = document.createElement('div');
        loading.style.gridColumn = '1 / -1';
        loading.style.textAlign = 'center';
        loading.style.padding = '20px';
        loading.style.color = '#707070';
        loading.textContent = 'Cargando disponibilidad...';
        grid.appendChild(loading);

        fetchAvailability(year, month);
    }

    function renderCalendarGrid() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth(); // 0-11
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const daysInMonth = lastDay.getDate();
        const startingDayOfWeek = firstDay.getDay();
        
        const grid = document.getElementById('calendar-grid');
        grid.replaceChildren(); // clear loading text

        // Re-add headers because we cleared the whole grid
        ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sab'].forEach(day => {
            const label = document.createElement('div');
            label.className = 'day-label';
            label.textContent = day;
            grid.appendChild(label);
        });

        for (let i = 0; i < startingDayOfWeek; i++) {
            grid.appendChild(document.createElement('div'));
        }
        
        for (let day = 1; day <= daysInMonth; day++) {
            const cell = document.createElement('div');
            cell.className = 'day-cell';
            cell.textContent = day;
            
            const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const dayData = currentAvailability[dateStr];

            if (dayData && dayData.available) {
                cell.classList.add('available');
                cell.addEventListener('click', () => selectDate(dateStr, dayData.slots));
            }
            
            grid.appendChild(cell);
        }
    }

    function selectDate(dateStr, slots) {
        selectedDateStr = dateStr;
        
        // Find Monday of that week
        const dateObj = new Date(dateStr + 'T00:00:00'); // Force local interpretation
        const dayOfWeek = dateObj.getDay();
        const diff = dateObj.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
        const monday = new Date(dateObj.getFullYear(), dateObj.getMonth(), diff);
        
        const start = new Date(monday);
        const end = new Date(monday);
        end.setDate(end.getDate() + 6);
        
        const monthNames = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        document.getElementById('week-label').textContent = 
            `${start.getDate()} - ${end.getDate()} de ${monthNames[start.getMonth()]}`;
        
        renderWeek(monday, slots); // Pass slots of the selected day for rendering the week
        setView('week');
    }

    function renderWeek(monday) {
        const grid = document.getElementById('week-grid');
        grid.replaceChildren();
        
        const days = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sab'];
        
        for (let i = 0; i < 7; i++) {
            const d = new Date(monday);
            d.setDate(d.getDate() + i);
            const dStr = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            
            const dayDiv = document.createElement('div');
            dayDiv.className = 'week-day';
            
            const labelDiv = document.createElement('div');
            labelDiv.className = 'week-day-label';
            labelDiv.textContent = days[d.getDay()];
            
            const numDiv = document.createElement('div');
            numDiv.className = 'week-day-num';
            numDiv.textContent = d.getDate();
            
            dayDiv.appendChild(labelDiv);
            dayDiv.appendChild(numDiv);

            const dayData = currentAvailability ? currentAvailability[dStr] : null;

            if (dayData && dayData.available && dayData.slots && dayData.slots.length > 0) {
                const slotsContainer = document.createElement('div');
                slotsContainer.className = 'slots';
                
                dayData.slots.forEach(slot => {
                    if (slot.available) {
                        const btn = document.createElement('button');
                        btn.className = 'slot';
                        btn.textContent = slot.start;
                        btn.addEventListener('click', (e) => {
                            document.querySelectorAll('.slot').forEach(b => b.classList.remove('selected'));
                            btn.classList.add('selected');
                            selectSlot(dStr, slot);
                        });
                        slotsContainer.appendChild(btn);
                    }
                });
                dayDiv.appendChild(slotsContainer);
            } else {
                dayDiv.classList.add('week-unavailable');
                const unavailP = document.createElement('p');
                unavailP.style.fontSize = '11px';
                unavailP.style.color = '#505050';
                unavailP.textContent = 'No disponible';
                dayDiv.appendChild(unavailP);
            }
            
            grid.appendChild(dayDiv);
        }
    }

    function selectSlot(dateStr, slot) {
        selectedSlot = slot;
        const d = new Date(dateStr + 'T00:00:00');
        const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        document.getElementById('slot-info').textContent = 
            `${days[d.getDay()]} ${d.getDate()} a las ${slot.start}`;
        
        setView('form');
    }

    async function confirmBooking() {
        const nameInput = document.getElementById('pilar-name');
        const emailInput = document.getElementById('pilar-email');
        const subjectInput = document.getElementById('pilar-subject');
        const notesInput = document.getElementById('pilar-notes');
        
        let isValid = true;
        [nameInput, emailInput, subjectInput].forEach(input => {
            if (!input.value.trim()) {
                input.style.borderColor = '#ff4444';
                isValid = false;
            } else {
                input.style.borderColor = '#7c3aed';
            }
        });

        if (!emailInput.value.includes('@')) {
            emailInput.style.borderColor = '#ff4444';
            isValid = false;
        }
        
        if (!isValid || !activeService || !selectedDateStr || !selectedSlot) return;
        
        const btn = document.getElementById('confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Procesando...';

        try {
            const response = await fetch(restUrl + '/book', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify({
                    service_id: activeService.id,
                    client_name: nameInput.value.trim(),
                    client_email: emailInput.value.trim(),
                    notes: notesInput.value.trim() ? `${subjectInput.value.trim()} - ${notesInput.value.trim()}` : subjectInput.value.trim(),
                    appointment_date: selectedDateStr,
                    start_time: selectedSlot.start + ':00',
                    end_time: selectedSlot.end + ':00',
                    price: currentAvailability[selectedDateStr].price || 0,
                    currency: activeService.currency || 'USD'
                })
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Error al agendar');
            }

            if (data.url) {
                window.location.href = data.url;
            } else {
                setView('success');
            }
        } catch (error) {
            console.error(error);
            alert('Error al intentar agendar la reunión. Por favor intenta de nuevo.');
            btn.disabled = false;
            btn.textContent = 'Confirmar reunión';
        }
    }

    function prevMonth() { 
        currentDate.setMonth(currentDate.getMonth() - 1); 
        renderCalendar(); 
    }
    function nextMonth() { 
        currentDate.setMonth(currentDate.getMonth() + 1); 
        renderCalendar(); 
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', init);

})();
