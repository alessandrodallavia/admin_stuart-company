# Catalogo CRM per calcolatori e configuratori

La landing e l'admin usano il calcolo condiviso in `public_html/app/Support/CrmHoodieCatalog.php`, leggendo lo stesso database CRM. L'admin trova la landing tramite `config/landing_quote.php` (`LANDING_PATH` se le cartelle non sono affiancate). Entrambe le applicazioni devono essere distribuite insieme; il database della landing deve essere quello contenente le tabelle CRM, come verificato nella configurazione locale. Il calcolo non invia messaggi WhatsApp automaticamente.

## Attivazione e deploy

1. Distribuire le modifiche di admin e public_html e gli asset compilati della landing.
2. Eseguire `php artisan migrate --force` dall'admin. Sono necessarie le migrazioni del catalogo già presenti, quella delle associazioni `2026_11_05_000002` e quella dello snapshot `2026_11_05_000003`. Quest'ultima esiste anche nella landing per supportare il deployment indipendente; sul database condiviso viene registrata una sola volta. Non reimportare i listini SQL per attivare il collegamento.
3. Svuotare le cache applicative/config/view secondo il processo di deploy delle due applicazioni.
4. In Catalogo CRM → Prodotti per calcolatore verificare prodotti e lavorazioni. Abilitare le associazioni da pubblicare e rendere attivi/visibili sul sito i relativi prodotti. Le nove associazioni iniziali sono disabilitate: fino all'abilitazione il sito propone una richiesta di preventivo, senza recuperare il vecchio prezzo.
5. Confrontare una quotazione sul sito con la stessa nell'admin WhatsApp. La selezione del modello e della qualità deve corrispondere allo stesso prodotto CRM. L'admin usa prodotti attivi anche se non pubblicati sul sito.

## Regole

- Solo prezzi di vendita CRM; costi, margini e informazioni interne dei fornitori non vengono serializzati nel catalogo pubblico.
- Quantità intere 10–3000 per felpe e 15–2999 per T-shirt. Si usa la fascia che contiene la quantità effettiva, inclusi scaglioni a 20 pezzi. Oltre la soglia della famiglia: quotazione manuale.
- HEART → STAMPA_CUORE; STANDARD → STAMPA_FRONT; LARGE → STAMPA_BACK; MAX → STAMPA_MAX. Per due lati si sommano le due applicazioni. Solo lavorazioni attive abilitate esplicitamente per prodotto e posizione, incluse stampe e ricami. Le regole del prodotto determinano le posizioni disponibili.
- Prezzo unitario = capo + applicazioni, arrotondato a due decimali. Subtotale = unitario arrotondato × quantità. IVA 22% sul subtotale, arrotondata ai centesimi. Spedizione 9,90 € IVA inclusa se i prodotti IVA inclusa sono sotto 250 €, altrimenti gratuita. Il motore della scheda ordini preesistente non è stato modificato.
- Scaglioni mancanti o stampa non abilitata: quotazione su richiesta, senza prezzo storico o prezzo zero presentato come offerta.
- Colori e taglie provengono dal CRM e rispettano `sizes_restricted`. Il configuratore mostra le taglie previste per il colore: non effettua una ripartizione delle quantità per taglia e non dichiara giacenze.
- Le immagini vengono associate solo ai nove codici/profili già noti e agli espliciti codici colore. Se si cambia prodotto lo strumento continua a leggere colori/prezzi, ma non riutilizza la foto di un altro prodotto. Le anteprime Makito esistenti restano indicative.
- Prima del salvataggio il server rilegge catalogo, associazione, tariffa e colore. Se il prodotto/prezzo è cambiato, rifiuta la richiesta e invita a ricaricare. Le nuove richieste conservano `calculator_crm_snapshot` con prodotto, variante, importi in centesimi, aliquota e regole di spedizione. Non modifica retroattivamente richieste e ordini confermati. La migrazione di rollback conserva lo snapshot per non perdere lo storico.
- Le tre associazioni T-shirt restano da scegliere dall’admin. Fino alla prima assegnazione, calcolatore e configuratore T-shirt usano il listino precedente. Dalla prima assegnazione usano il CRM: slot senza prodotto attivo/visibile e associazione abilitata passano a quotazione su richiesta. Si possono configurare tutti e tre i prodotti prima di abilitarli. I prezzi sono verificati sul server anche per le richieste WhatsApp T-shirt; le stampe MAX vengono calcolate automaticamente quando STAMPA_MAX è associata e dispone della tariffa per la quantità selezionata. Non sono state scelte associazioni T-shirt durante lo sviluppo. Le foto T-shirt non vengono riutilizzate automaticamente dopo l’assegnazione: servono corrispondenze verificate prodotto/colore, e in loro assenza compare l’anteprima non disponibile. Il pannello “Prepara preventivo prodotti” nella chat admin permette di scegliere Felpe oppure T-shirt. Confronta le qualità con i prodotti CRM associati, inclusi quelli non pubblicati sul sito purché attivi, e genera un’anteprima con IVA, spedizione e totale ivato. Le richieste T-shirt già presenti precompilano famiglia e selezione. La generazione non salva né invia messaggi: l’operatore deve inserire il testo nel campo messaggio e inviarlo esplicitamente. Le schede promozionali dei modelli speciali sono separate dagli slot standard delle felpe.

## Verifiche

Test feature admin: associazioni e autorizzazioni; anteprima WhatsApp senza invio, quantità, stampe, totali e IVA. Test landing: catalogo reale su schema CRM isolato, abilitazione/visibilità, scaglioni 19/20, vincoli colore-taglia, dati pubblici, prezzi modificati o manipolati, snapshot e richiesta manuale. Test JavaScript: calcolo, scaglioni CRM, identificatori prodotto e riutilizzo controllato delle immagini.

Nessun deployment su Plesk e nessuna abilitazione pubblica automatica eseguiti durante lo sviluppo.


## Mockup per colore

Nella scheda prodotto è disponibile “Mockup per colore”. Si seleziona un colore e si associano separatamente fronte e retro, scegliendo dalla libreria pubblica esistente oppure caricando PNG/JPG/WebP (massimo 8 MB, massimo 5000 × 5000 px). La libreria ha ricerca per nome/cartella e mostra 24 anteprime per volta. Le modifiche richiedono il salvataggio esplicito; i permessi sono quelli del catalogo.

È richiesta la migrazione admin `2026_11_05_000004_add_mockup_paths_to_crm_product_colors.php`. Non associa automaticamente nessuna foto ai prodotti: la corrispondenza prodotto/colore va verificata dall'operatore. Le immagini sono proprietà del colore del prodotto, non della fascia Economic/Basic/Premium, e seguono quindi qualsiasi nuova associazione dello stesso prodotto. Le foto manualmente associate prevalgono sulle vecchie corrispondenze delle felpe; una rimozione esplicita non ripristina automaticamente la foto precedente. Le associazioni precedenti delle felpe restano come compatibilità finché il colore non viene personalizzato.

I caricamenti vengono salvati nel disco admin già esistente `live_mockup_assets`, sottocartella `crm/`, condiviso con la directory pubblica della landing `/assets/images/landing/live-mockup/`. In deploy verificare che questo disco punti effettivamente a tale directory e che sia scrivibile dall'admin (`LIVE_MOCKUP_ASSET_PATH` per configurazioni personalizzate). Distribuire entrambi i progetti e le nuove build. I file caricati sono asset pubblici destinati al configuratore; non usare questa libreria per documenti privati. Non sovrascrivere/cancellare la cartella `crm/` con il deploy degli asset statici.

Scollegare o sostituire una foto non elimina il file, perché può essere riutilizzato da altri colori o prodotti. File privati, percorsi esterni alla libreria, SVG e contenuti non immagine vengono rifiutati. In caso di errore nel salvataggio vengono rimossi i nuovi caricamenti della richiesta fallita.


### Riutilizzo dei mockup precedenti

La migrazione `2026_11_05_000005_restore_legacy_crm_mockups.php` collega i file precedenti tramite la tabella esplicita `config/crm_legacy_mockups.json`: Economic RY6424 (11 colori), Basic BS010 (34), Premium BS052 (36), oltre alle nove felpe (147 colori). Non usa i nomi modificabili dei colori o gli HEX per indovinare l'associazione. Anche se si cambia lo slot di vendita, le foto seguono il codice prodotto e il colore CRM.

Sono previste 228 coppie fronte/retro. La migrazione associa solo colori ancora privi di scelte personalizzate e solo se esistono entrambi i file. Il colore SY / Sun Yellow di BS331 non ha immagini nel vecchio set e resta non associato. Le immagini Makito già usate nella landing rimangono anteprime indicative riutilizzate, non nuovi packshot ufficiali.

Verifica senza modifiche: `php artisan crm:restore-legacy-mockups`. Se i file sul server vengono caricati dopo la migrazione, si possono completare le associazioni mancanti con `php artisan crm:restore-legacy-mockups --apply`. Le associazioni manuali e le rimozioni esplicite vengono sempre conservate. Il rollback della migrazione dati mantiene le associazioni per non perdere eventuali revisioni dell'operatore.


## Personalizzazioni gestite dal CRM

La migrazione `2026_11_06_000001_configure_crm_personalizations.php` aggiunge tecnica (stampa/ricamo/altra lavorazione/impianto), area dell'anteprima, dimensioni descrittive, posizioni fronte/retro, impianto dipendente e prezzo unico IVA esclusa. I ricami esistenti vengono collegati a IMPIANTO_RICAMO; i vecchi scaglioni dell'impianto sono conservati come storico e non vengono usati dai tre calcolatori. Il prezzo unico confermato è 35 € + IVA per ricamo; la migrazione `2026_11_06_000002_set_embroidery_setup_fixed_price.php` lo imposta su IMPIANTO_RICAMO se il campo è ancora vuoto, senza sovrascrivere successive scelte manuali. È modificabile nella scheda lavorazione.

Calcolatore, configuratore e preventivo WhatsApp leggono le associazioni esplicite del prodotto per Fronte, Retro e Manica. Non generano combinazioni automatiche. Gli impianti non sono opzioni autonome per il cliente: vengono aggiunti come dipendenza del ricamo. Nomi, posizioni, dimensioni e immagine indicativa seguono i metadati del CRM. I vecchi identificatori delle stampe vengono mantenuti per le richieste già salvate. Le nuove lavorazioni usano identificatori basati sull'ID CRM. Le associazioni per posizione configurate sul prodotto sono la fonte delle compatibilità.

Formula: imponibile = prezzo unitario arrotondato di capo + lavorazioni, moltiplicato per i capi, più i costi unici degli impianti. Ogni ricamo selezionato richiede un impianto: fronte e retro ricamati richiedono due impianti; una stampa abbinata a un ricamo ne richiede uno. La quantità dei capi non moltiplica il costo unico. IVA e soglia di spedizione si calcolano sull'imponibile comprensivo degli impianti. Il costo è mostrato separatamente e conservato nello snapshot del preventivo.

Un ricamo senza tariffa per la quantità scelta, senza impianto attivo oppure senza prezzo unico configurato non produce un prezzo automatico. Prima di abilitare pubblicamente un ricamo viene verificato il suo impianto. L'anteprima mostra soltanto il posizionamento indicativo della grafica e non simula i fili del ricamo.

Questa modifica riguarda landing e generatore preventivi WhatsApp. Il motore preesistente di ricalcolo della scheda ordini non viene migrato automaticamente alle dipendenze/impianti dei calcolatori: gli scaglioni storici degli ordini non vengono riscritti. Applicare la migrazione admin prima di distribuire/attivare il nuovo listino della landing; in mancanza dello schema i calcolatori rifiutano il prezzo automatico. Distribuire entrambi i progetti e le due build, poi svuotare le cache.

### Tempi e quantità minime

Le configurazioni che includono almeno un ricamo prevedono spedizione entro 12 giorni lavorativi, anche nelle combinazioni stampa + ricamo. Le sole stampe mantengono i tempi standard. Il valore segue la selezione nelle landing, nel messaggio WhatsApp dell'admin e nei campi della richiesta salvata.

Quando la quantità è inferiore alla prima fascia effettivamente quotabile per prodotto e personalizzazioni selezionati, viene indicato il minimo reale (per esempio “Questa personalizzazione richiede almeno 30 pezzi”). Il minimo considera anche capo, tariffe delle lavorazioni e disponibilità dell'impianto. Se manca una tariffa per un'altra ragione, non viene inventata una quantità minima.


## Associazioni esplicite per posizione (6 novembre 2026)

Applicare dall’admin le migrazioni `2026_11_06_000003_create_product_position_personalizations.php` e `2026_11_06_000004_add_sleeve_graphic_to_leads.php`, distribuendo entrambi i progetti e le build. La seconda migrazione è condivisa anche dalla landing e viene eseguita una sola volta sul database comune.

In Catalogo CRM → prodotto → Personalizzazioni, selezionare le lavorazioni consentite nelle colonne Fronte, Retro e Manica, quindi salvare. Le regole appartengono al prodotto, anche se associato a più qualità. Nessuna associazione viene creata automaticamente: il vecchio elenco dello slot e le vecchie posizioni globali non abilitano le nuove scelte. Configurare esplicitamente i prodotti prima di esporre il nuovo flusso. Con lo schema nuovo anche le T-shirt usano esclusivamente il CRM; non recuperano il vecchio listino in assenza di associazioni.

Il cliente sceglie separatamente una lavorazione o Nessuna per ogni posizione disponibile. Una posizione senza associazioni è nascosta. Almeno una personalizzazione è richiesta per questo flusso. Il server ricontrolla prodotto e posizione; tariffe e nomi provengono dal CRM. Le grafiche manica hanno un caricamento separato e vengono conservate nelle richieste, senza inventare una foto del capo di profilo.

Gli impianti restano dipendenze, non scelte autonome: ogni posizione ricamata aggiunge un costo unico di 35 € + IVA, quindi tre ricami aggiungono tre impianti. Ricami e combinazioni miste prevedono 12 giorni lavorativi. I minimi reali delle tariffe vengono mostrati al cliente. I vecchi preventivi e gli scaglioni degli ordini restano conservati.
