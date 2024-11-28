function updateClock() {
    const now = new Date();
    const pad = (num) => num.toString().padStart(2, '0');

    const hours = pad(now.getHours());
    const minutes = pad(now.getMinutes());
    const seconds = pad(now.getSeconds());
    const day = now.toLocaleDateString('en-us', { weekday: 'long' });
    const date = now.toLocaleDateString('en-us', { month: 'long', day: 'numeric', year: 'numeric' });

    document.getElementById('clock').innerHTML = `${day}, ${date} ${hours}:${minutes}:${seconds}`;
}

setInterval(updateClock, 1000);
updateClock();
