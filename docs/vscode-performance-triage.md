# VS Code Performance Triage

Target: reduce extension host crashes, high CPU, and memory spikes without changing project code.

## What the logs show

- Extension host is crashing and restarting automatically.
- Renderer and plugin host show listener/input leak warnings.
- The active window loads many startup extensions at once.
- The heaviest active services in this workspace are:
  - `GitHub.copilot-chat`
  - `ms-python.python`
  - `ms-python.vscode-pylance`
  - `zobo.php-intellisense`
  - `bradlc.vscode-tailwindcss`
  - `dbaeumer.vscode-eslint`
  - `ms-vsliveshare.vsliveshare`
  - `ritwickdey.LiveServer`
  - `ms-vscode-remote.remote-containers`
  - `ms-vscode-remote.remote-wsl`
  - `redhat.java` and `vscjava.*`

## Disable first

If you only want to keep the current Laravel/PHP workspace stable, disable these first:

1. `redhat.java`
2. `vscjava.vscode-gradle`
3. `vscjava.vscode-java-pack`
4. `vscjava.vscode-java-debug`
5. `vscjava.vscode-java-test`
6. `vscjava.vscode-java-upgrade`
7. `ms-vscode-remote.remote-containers`
8. `ms-vscode-remote.remote-wsl`
9. `ms-vsliveshare.vsliveshare`
10. `ritwickdey.LiveServer`
11. `gornivv.vscode-flutter-files`
12. `dart-code.dart-code`
13. `dart-code.flutter`
14. `msjsdiag.vscode-react-native`
15. `visualstudioexptteam.vscodeintellicode`
16. `visualstudioexptteam.intellicode-api-usage-examples`

## Keep enabled for this repo

- `bmewburn.vscode-intelephense-client`
- `shufo.vscode-blade-formatter`
- `bradlc.vscode-tailwindcss`
- `dbaeumer.vscode-eslint`
- `esbenp.prettier-vscode`
- `xdebug.php-debug`
- `ryannaddy.laravel-artisan`
- `laravel.vscode-laravel`

## If VS Code still freezes

Run `Help: Start Extension Bisect` and keep bisecting until the crash disappears.

## Why the host restarts

- `renderer.log` shows the extension host became unresponsive, then VS Code terminated it and launched a fresh host.
- That behavior is normal recovery, not a project code restart.
- The crash is most likely caused by extension load pressure, not by Laravel itself.
