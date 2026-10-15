Set WshShell = CreateObject("WScript.Shell")
WshShell.CurrentDirectory = "c:\wamp64\www\taskmanager\dispatcher"
WshShell.Run """C:\Users\DELL\AppData\Local\Programs\Python\Python311\pythonw.exe"" ""c:\wamp64\www\taskmanager\dispatcher\dispatcher.py""", 0, False

