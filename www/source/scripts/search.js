function filterContacts() {
    const input = document.getElementById('contactSearch');
    const filter = input.value.toLowerCase();
    const chatItems = document.querySelectorAll('.chat-item');
    
    chatItems.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(filter)) {
            item.classList.remove('hidden');
        } else {
            item.classList.add('hidden');
        }
    });
}