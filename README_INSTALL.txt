WIEDERKEHRENDE WECKRUFE – FreePBX 15, Version 15.0.2.0

NEU IN 15.0.2.0
- Vorzeitiges Auflegen ohne Taste 1 oder 5 wird als nicht bestätigt erkannt.
- Nach vorzeitigem Auflegen wird gemäß Wiederholungszeit und maximaler Versuchszahl neu angerufen.
- Telegram-Einstellungen direkt im Modul: aktivieren, Bot-Token, Chat-ID und Testnachricht.
- Telegram-Meldungen bei Bestätigung, Snooze, fehlender Eingabe, Auflegen und endgültigem Fehlschlag.

WICHTIG
- Vor der Installation ein FreePBX-Backup oder VM-Snapshot erstellen.
- Externe Ziele laufen über Local/<Nummer>@from-internal und damit über die normalen Ausgangsrouten.

UPDATE / INSTALLATION PER SSH
1. Archiv nach /root kopieren.
2. Entpacken und Modulverzeichnis ersetzen:
   rm -rf /tmp/recurringwakeup
   tar xzf recurringwakeup-15.0.2.0.tar.gz -C /tmp
   cp -a /tmp/recurringwakeup /var/www/html/admin/modules/
3. Rechte und Update:
   fwconsole chown
   fwconsole ma install recurringwakeup
   fwconsole ma enable recurringwakeup
   fwconsole reload
4. Browser neu laden. Menü: Anwendungen -> Wiederkehrende Weckrufe

PRÜFUNG
- php -l /var/www/html/admin/modules/recurringwakeup/page.recurringwakeup.php
- php -l /var/www/html/admin/modules/recurringwakeup/agi-bin/recurringwakeup_agi.php
- asterisk -rx "dialplan show recurringwakeup-answer"
- tail -f /var/log/asterisk/full

TELEGRAM
- Bot-Token vollständig aus BotFather eintragen.
- Chat-ID eintragen; Gruppen-IDs beginnen häufig mit -100.
- Telegram aktivieren, speichern und danach Testnachricht senden.
- Der Bot muss im Zielchat Mitglied sein und Nachrichten senden dürfen.

HINWEISE
- Scheduler läuft minütlich; ein Testanruf kann bis zu 60 Sekunden benötigen.
- Taste 1 bestätigt; Taste 5 erzeugt einen neuen Termin nach der Schlummerzeit.
- Keine Eingabe oder sofortiges Auflegen erzeugt einen neuen Versuch.
- Bestehende Weckrufe und Protokolle bleiben beim Update erhalten.
