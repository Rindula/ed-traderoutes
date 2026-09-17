/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const root = document.querySelector('#dashboard');
if (root) {
    let refreshTimer;
    const endpoint = root.dataset.dashboardEndpoint;
    const text = (id, value) => { const element = document.querySelector(`#${id}`); if (element) element.textContent = value ?? '—'; };
    const money = value => `${new Intl.NumberFormat('de-DE').format(Math.round(value || 0))} Cr/h`;
    const render = data => {
        const route = data.activeRoute;
        const leg = route?.currentLeg;
        text('plugin-status', data.plugin?.status === 'active' ? 'Plugin aktiv' : 'Manueller Modus');
        document.querySelector('#plugin-status')?.classList.toggle('status-pill--warning', data.plugin?.status !== 'active');
        text('current-route', leg ? `${leg.sourceSystem} → ${leg.destinationSystem}` : 'No active route');
        text('current-action', leg ? `Buy ${leg.commodity} at ${leg.sourceStation}; sell at ${leg.destinationStation} (${leg.quantity} t)` : 'Calculate a route to get started.');
        text('current-profit', route ? money(data.metrics?.creditsPerHour) : '—');
        const stops = document.querySelector('#next-stops');
        if (stops) stops.innerHTML = (route?.nextStops || []).map((stop, index) => `<li><span>${index + 1}</span>${stop.system || 'Unknown'} · ${stop.station || 'Unknown'}</li>`).join('') || '<li class="muted">No preview available</li>';
        const metricItems = [['Credits/hour', money(data.metrics?.creditsPerHour)], ['Profit', `${new Intl.NumberFormat('de-DE').format(data.metrics?.profitCredits || 0)} Cr`], ['Jumps', data.metrics?.jumps || 0], ['Stops', data.metrics?.tradeStops || 0]];
        document.querySelector('#metrics').innerHTML = metricItems.map(([label, value]) => `<div><dt>${label}</dt><dd>${value}</dd></div>`).join('');
        const cargo = data.cargo || {};
        document.querySelector('#cargo').innerHTML = [['Cargo', `${cargo.usedCapacity || 0} / ${cargo.capacity || 0} t`], ['Sync', data.plugin?.heartbeatFresh ? 'Fresh' : 'Stale'], ['Mode', data.plugin?.mode === 'active' ? 'Automatic' : 'Manual'], ['Safety', cargo.uncertain ? 'Cargo uncertain' : 'Confirmed']].map(([label, value]) => `<div><dt>${label}</dt><dd>${value}</dd></div>`).join('');
        text('updated', data.routeCalculatedAt ? `Last route calculation: ${new Date(data.routeCalculatedAt).toLocaleString('de-DE')}` : 'No calculation yet');
    };
    const refresh = async () => { try { const response = await fetch(endpoint, { headers: { Accept: 'application/json' } }); if (!response.ok) throw new Error('Dashboard unavailable'); render(await response.json()); document.querySelector('#dashboard-error').hidden = true; } catch (error) { const notice = document.querySelector('#dashboard-error'); notice.textContent = error.message; notice.hidden = false; } };
    const debouncedRefresh = () => { clearTimeout(refreshTimer); refreshTimer = setTimeout(refresh, 350); };
    refresh();
    setInterval(refresh, 60000);
    window.addEventListener('ed-traderoutes:market-update', debouncedRefresh);
    window.addEventListener('ed-traderoutes:player-context-change', debouncedRefresh);
    window.addEventListener('ed-traderoutes:filter-change', debouncedRefresh);
}
