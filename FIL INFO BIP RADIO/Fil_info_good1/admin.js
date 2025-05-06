document.addEventListener('DOMContentLoaded', function () {
    const btnAjouterBloc = document.getElementById('ajouter-bloc');
    const container = document.getElementById('lnb-container');

    // Ajouter un nouveau bloc
    btnAjouterBloc.addEventListener('click', function () {
        const block = document.createElement('div');
        block.classList.add('lnb-block');
        block.innerHTML = `
            <input type="time" name="heure[]" required />
            <input type="text" name="icone[]" placeholder="Icône (ex: ⚖️)" required />
            <textarea name="contenu[]" placeholder="Contenu..." required></textarea>
            <button type="button" class="supprimer-bloc">Supprimer</button>
        `;
        container.appendChild(block);
    });

    // Supprimer un bloc (gestionnaire d'événements délégué)
    container.addEventListener('click', function (e) {
        if (e.target.classList.contains('supprimer-bloc')) {
            e.target.closest('.lnb-block').remove();
        }
    });
});
