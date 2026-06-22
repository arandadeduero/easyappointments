# EasyAppointments Local Print Service

This service runs on the kiosk computer and prints in-person appointment tickets.

## Setup

1. Install Node.js on the kiosk computer.
2. Copy `config.example.json` to `config.json`.
3. Set the same token in the EasyAppointments provider settings and in `config.json`.
4. Add the EasyAppointments URL origin to `allowedOrigins`, for example `http://192.168.1.224` or `https://citas.example.com`.
5. Optionally set `printerName` to the exact Windows printer name. Leave empty to use the default printer.
6. Adjust `paperWidthMm` and `paperHeightMm` to match the Windows printer paper size.
7. Start the service:

```powershell
npm start
```

Health check:

```text
http://127.0.0.1:8787/health
```

## EasyAppointments Settings

Enable local printing in the provider/department settings:

- Endpoint: `http://127.0.0.1:8787/print`
- Token: must match `config.json`

## Notes

- The service listens on `127.0.0.1` by default, so it is only reachable from the kiosk itself.
- The service prints through the installed Windows printer driver using a compact graphical ticket layout.
- If the printer feeds too much paper, reduce `paperHeightMm` and make sure the Windows printer paper size matches it.
- If nothing is printed, confirm that `printerName` matches the Windows printer name or leave it empty to use the default printer.
- For production, keep it running with NSSM, Task Scheduler, PM2, or a Windows service wrapper.
