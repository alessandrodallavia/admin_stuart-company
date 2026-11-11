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
5. Selezionare le taglie con i pulsanti standard, bambino e taglia unica. **Seleziona XS–XXL** aggiunge il gruppo standard. Per altre misure usare **Altra taglia**, poi **Aggiungi taglia** o Invio. Le taglie scelte appaiono come etichette rimovibili nell'ordine di selezione. La lista definisce le taglie generali del prodotto. Per ciascun colore scegli **Tutte le taglie del prodotto** oppure **Taglie specifiche**, quindi apri **Seleziona le taglie consentite** e spunta quelle previste. Il riepilogo mostra subito la selezione. Una selezione specifica vuota disabilita tutte le taglie di quel colore; non viene interpretata come “tutte”.
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

## Taglie per colore e schede tecniche del fornitore

Applicare anche `2026_11_05_000001_connect_crm_supplier_details_and_color_sizes.php` con `php artisan migrate --force`, quindi pubblicare gli asset ricostruiti con `npm run build`. La migrazione riconosce le due tabelle eventualmente già create dall'import Camac: le conserva e aggiunge la distinzione tra taglie ereditate e taglie specifiche. Non occorre ricaricare il file SQL del catalogo. La migrazione non è stata applicata al database reale durante lo sviluppo.

I colori con un sottoinsieme già importato vengono impostati su **Taglie specifiche**. Per BS332, BK mantiene XS–5XL e gli altri colori XS–4XL. I colori che hanno tutte le taglie e quelli preesistenti senza matrice ereditano le taglie generali. Aggiungendo una nuova taglia generale, questa si abilita automaticamente solo sui colori che ereditano tutte le taglie. Sui colori specifici deve essere selezionata esplicitamente. Rimuovendo una taglia generale, viene rimossa anche dagli abbinamenti dei colori.

Il salvataggio aggiorna la matrice `crm_product_color_sizes` in transazione insieme alle varianti, conserva gli ID esistenti e rifiuta taglie non presenti nel prodotto. Le richieste precedenti senza campi matrice conservano le restrizioni esistenti. Le combinazioni non rappresentano le giacenze e non cambiano i prezzi. Ordini, sito, configuratore e calcolatore non applicano ancora questi vincoli: questa modifica riguarda la gestione nell'admin del catalogo.

La sezione **Scheda prodotto · [fornitore]** mostra composizione, grammatura, dettagli e data di verifica salvati in `crm_product_supplier_details`. Il pulsante **Apri scheda tecnica PDF** apre il documento ufficiale in una nuova scheda; il link del fornitore rimane distinto dall'eventuale PDF privato caricato in **Documenti e riferimenti**. I dati originali del fornitore sono in consultazione. Il caricamento, la sostituzione, la rimozione e il download del PDF privato mantengono i permessi del catalogo. Anche gli utenti con sola lettura possono consultare la scheda del fornitore e le taglie ammesse per ciascun colore.

Il rollback della nuova migrazione elimina le due tabelle aggiuntive, inclusi dati del fornitore e abbinamenti: usare il normale deploy in avanti per conservare l'import.
