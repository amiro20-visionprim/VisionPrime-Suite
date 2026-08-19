Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "cmd /c cd /d ""C:\Users\Armita\Documents\Vsionprime SUITE\workspace-arena-suite\vision-prime"" && ""C:\Users\Armita\Documents\Vsionprime SUITE\workspace-arena-suite\.tools\php\php.exe"" artisan serve --host=127.0.0.1 --port=8000 > /dev/null 2>&1", 0, False
