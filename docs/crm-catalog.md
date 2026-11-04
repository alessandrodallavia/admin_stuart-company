# Anagrafiche del catalogo CRM

Il catalogo ora gestisce brand, fornitori e i dettagli dei prodotti. Non modifica i prezzi, le fasce, lo storico degli ordini o i listini delle landing.

## Aggiornamento dell'installazione

Dopo il backup e il deploy, dalla directory dell'admin eseguire `php artisan migrate --force` per applicare `2026_11_04_000001_extend_crm_product_catalog.php`. La migrazione è aggiuntiva: conserva prodotti e fasce esistenti. Non eseguire nuovamente l'SQL di sostituzione del listino, che cancellerebbe le nuove anagrafiche dei prodotti.

Se la procedura di deploy lo prevede, ricostruire gli asset con `npm run build` e aggiornare le cache Laravel. Nessuna migrazione è stata eseguita sul database reale durante lo sviluppo.

## Utilizzo

1. In Catalogo CRM, selezionare la scheda **Fornitori** e usare **Nuovo fornitore** per aggiungere le anagrafiche con nome e sito web facoltativo.
2. Selezionare **Brand**, creare i brand e selezionare i fornitori che li distribuiscono. Un fornitore può distribuire più brand; un brand può avere più fornitori.
3. In **Prodotti**, scegliere **Nuovo prodotto** oppure **Apri scheda** sulla riga esistente. La finestra **Anagrafica prodotto** contiene dati principali, brand, fornitori, colori, taglie, documenti e visibilità; **Fasce prezzo** contiene il listino. Si possono lasciare i dati non ancora conosciuti vuoti.
4. Usare **Aggiungi colore** per inserire una riga con nome, codice del fornitore e campione. Il nome è obbligatorio; codice e campione sono facoltativi. Il pallino apre il selettore colore; modificando il campo HEX l'anteprima si aggiorna immediatamente. Il pallino tratteggiato indica un campione non definito. Con il solo nome viene usato lo stesso valore come codice. Il pulsante × rimuove la riga; il salvataggio conferma le modifiche.
5. Selezionare le taglie con i pulsanti standard, bambino e taglia unica. **Seleziona XS–XXL** aggiunge il gruppo standard. Per altre misure usare **Altra taglia**, poi **Aggiungi taglia** o Invio. Le taglie scelte appaiono come etichette rimovibili nell'ordine di selezione. La lista indica le taglie del prodotto in generale: non rappresenta ancora una matrice disponibilità colore/taglia.
6. Allegare una scheda tecnica PDF fino a 10 MB e, facoltativamente, il link alla pagina del prodotto del fornitore. Il PDF viene conservato nel disco privato e scaricato con gli stessi permessi di lettura del catalogo. Il limite effettivo dipende anche dai limiti upload PHP dell'hosting.
7. Impostare la visibilità sul sito. I prodotti esistenti e i nuovi prodotti sono nascosti per impostazione predefinita. Questo campo prepara la futura integrazione: il sito, il configuratore e la chat WhatsApp non sono ancora collegati al catalogo CRM.

Gli utenti con sola lettura vedono le anagrafiche e possono scaricare le schede; la modifica richiede `crm_catalog.manage`. La modalità formazione mantiene il blocco del catalogo già previsto dall'applicazione.

Le schede vengono sostituite o rimosse esplicitamente. La cancellazione di un prodotto elimina il suo PDF, colori, taglie e associazioni ai fornitori. Brand e fornitori associati a prodotti non possono essere eliminati. Non si può rimuovere dal brand un fornitore ancora utilizzato dai prodotti di quel brand.

Immagini, prezzi specifici per fornitore, supplementi taglia, giacenze e abbinamenti alle lavorazioni non fanno parte di questo aggiornamento.

## Navigazione e finestre

La pagina separa Prodotti, Stampe, Brand, Fornitori e Categorie lead. La ricerca filtra prodotti e stampe; nella lista prodotti sono disponibili anche filtri per brand e visibilità. Gli importi sono allineati a destra e indicati come IVA esclusa. Su schermi piccoli i prodotti e le fasce vengono presentati come schede.

Le finestre chiuse sono conservate in template inerti: i loro moduli vengono inseriti nel documento solo all'apertura e rimossi alla chiusura. In questo modo il focus sui campi e la compilazione automatica del browser non devono esaminare tutti i moduli del catalogo. Annulla e Chiudi scartano le modifiche non salvate; aprendo una fascia prezzo la scheda di provenienza resta invece disponibile per il ritorno.

Creazione, modifica delle anagrafiche e modifica delle fasce avvengono in finestre a comparsa. Escape, il pulsante Chiudi e il clic esterno chiudono la finestra. Dopo la modifica di una fascia si torna alla scheda di provenienza con il focus sul pulsante utilizzato. In caso di errori di validazione il modulo si riapre conservando i valori inseriti; per motivi del browser i file da caricare devono essere riselezionati. Le eliminazioni richiedono una conferma nella relativa finestra.

Questo ridisegno non aggiunge migrazioni. Dopo il deploy ricostruire e pubblicare gli asset con `npm run build`, oltre ad aggiornare le viste e il JavaScript.
