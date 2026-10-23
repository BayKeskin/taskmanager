Set fso = CreateObject("Scripting.FileSystemObject")
Set WshShell = CreateObject("WScript.Shell")

currentDir = fso.GetParentFolderName(WScript.ScriptFullName)
dispatcherScript = fso.BuildPath(currentDir, "dispatcher.py")
WshShell.CurrentDirectory = currentDir

userProfile = WshShell.ExpandEnvironmentStrings("%USERPROFILE%")
localAppData = WshShell.ExpandEnvironmentStrings("%LOCALAPPDATA%")

' Standart Python yollarını dinamik olarak ara
pythonwExe = ""
candidatePaths = Array(_
    localAppData & "\Programs\Python\Python311\pythonw.exe", _
    localAppData & "\Programs\Python\Python312\pythonw.exe", _
    localAppData & "\Programs\Python\Python310\pythonw.exe", _
    localAppData & "\Programs\Python\Python313\pythonw.exe", _
    "C:\Python311\pythonw.exe", _
    "C:\Python312\pythonw.exe" _
)

For Each p In candidatePaths
    If fso.FileExists(p) Then
        pythonwExe = p
        Exit For
    End If
Next

If pythonwExe = "" Then
    pythonwExe = "pythonw.exe"
End If

WshShell.Run """" & pythonwExe & """ """ & dispatcherScript & """", 0, False
