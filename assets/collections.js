document.addEventListener('click', (event) => {
    const add = event.target.closest('[data-collection-add]');
    if (add) {
        const collection = add.closest('[data-collection]');
        const index = Number(collection.dataset.index);
        const entry = document.createElement('div');
        entry.className = 'border rounded p-3 mb-3';
        entry.dataset.collectionEntry = '';
        // The prototype comes from the server's escaped Symfony form template.
        entry.innerHTML = collection.dataset.prototype.replaceAll('__name__', String(index));
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-outline-danger btn-sm';
        remove.dataset.collectionRemove = '';
        remove.textContent = 'Entfernen';
        entry.append(remove);
        collection.querySelector('[data-collection-items]').append(entry);
        collection.dataset.index = String(index + 1);
    }
    const remove = event.target.closest('[data-collection-remove]');
    if (remove) remove.closest('[data-collection-entry]').remove();
});
