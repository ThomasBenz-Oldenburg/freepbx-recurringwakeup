<img width="1536" height="1024" alt="6dbbc854-5d4b-446c-a58c-463825e5c669" src="https://github.com/user-attachments/assets/64c943b7-b6a5-48a8-a600-4d5a597b4609" />

# FreePBX Recurring Wakeup

A modern wake-up call module for **FreePBX 15** with automatic retries, snooze support, confirmation handling and optional Telegram notifications.

---

## Features

- One-time wake-up calls
- Configurable retry attempts
- Automatic retry after:
  - Hangup
  - No DTMF input
  - Unanswered call
- Snooze function (DTMF 5)
- Confirmation (DTMF 1)
- Optional Telegram notifications
- Detailed event logging
- German voice prompts
- Scheduler based on cron
- Easy installation

---

## Requirements

- FreePBX 15
- Asterisk 13 or newer
- PHP 7.x
- Linux

---

## Installation

1. Download the latest release.
2. Upload the module to your FreePBX server.
3. Install the module using Module Admin or:

```bash
fwconsole ma install recurringwakeup
fwconsole reload
```

4. Configure your wake-up schedules.

---

## Call Flow

1. User receives the wake-up call.
2. Press **1** to confirm.
3. Press **5** to activate Snooze.
4. If the call is terminated or no confirmation is received, the module automatically schedules the next retry.

---

## Telegram Notifications

Telegram support is optional.

Supported notifications include:

- Wake-up confirmed
- Snooze activated
- No response
- Call terminated
- Retry scheduled
- Maximum retry count reached

Simply configure:

- Bot Token
- Chat ID

inside the module settings.

---

## Event Log

Every wake-up attempt is logged.

Examples:

| Event | Description |
|-------|-------------|
| dialing | Outgoing call started |
| confirmed | User confirmed the wake-up |
| snooze | Snooze activated |
| noinput | No DTMF received |
| hangup | Call terminated without confirmation |
| retry | New wake-up scheduled |

---

## Current Features

- Automatic scheduler
- Retry management
- Confirmation handling
- Snooze
- Event logging
- Telegram integration

---

## Planned Features

- Additional languages
- More notification providers
- Web-based statistics
- Extended reporting
- Improved administration interface

Suggestions and pull requests are welcome.

---

## License

MIT License

---

## Author

Thomas Kersten

GitHub:
https://github.com/ThomasBenz-Oldenburg/freepbx-recurringwakeup

---

## Acknowledgements

Developed by Thomas Kersten with AI-assisted development using OpenAI ChatGPT.

---

## Disclaimer

This software is provided "as is" without warranty of any kind.
Please test thoroughly before using it in production environments.
