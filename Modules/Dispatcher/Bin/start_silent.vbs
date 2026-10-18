Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "c:\wamp64\www\taskmanager\Modules\Dispatcher\Bin"
WshShell.Run """C:\Users\DELL\AppData\Local\Programs\Python\Python311\pythonw.exe"" ""c:\wamp64\www\taskmanager\Modules\Dispatcher\Bin\dispatcher.py""", 0, False

