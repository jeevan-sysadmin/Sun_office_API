' Auto-Backup Service Installer
' Run as Administrator

Set objShell = CreateObject("WScript.Shell")
Set objFSO = CreateObject("Scripting.FileSystemObject")

' Get current directory
strCurrentDir = objFSO.GetAbsolutePathName(".")
strBatchFile = strCurrentDir & "\run_backup.bat"
strTaskName = "MySQL SunOffice AutoBackup"

' Colors for output
Const Blue = vbBlue
Const Green = vbGreen
Const Red = vbRed
Const White = vbWhite

WScript.Echo "=========================================="
WScript.Echo "  MySQL Auto-Backup Service Installer"
WScript.Echo "=========================================="
WScript.Echo ""

' Check if running as administrator
Set objWMI = GetObject("winmgmts:\\.\root\cimv2")
Set colProcesses = objWMI.ExecQuery("SELECT * FROM Win32_Process WHERE Name = 'WScript.exe'")

' Check if batch file exists
If Not objFSO.FileExists(strBatchFile) Then
    WScript.Echo "[ERROR] Batch file not found:"
    WScript.Echo "        " & strBatchFile
    WScript.Echo ""
    WScript.Echo "Please make sure run_backup.bat exists in this directory."
    WScript.Quit 1
End If

' Delete existing task if it exists
WScript.Echo "Removing existing task (if any)..."
objShell.Run "schtasks /delete /tn """ & strTaskName & """ /f", 0, True

' Create new task for system startup
WScript.Echo "Creating new startup task..."

strCommand = "schtasks /create /tn """ & strTaskName & """ " & _
             "/tr """ & strBatchFile & """ " & _
             "/sc onstart " & _
             "/ru SYSTEM " & _
             "/rl highest " & _
             "/f"

Set objExec = objShell.Exec(strCommand)
strOutput = objExec.StdOut.ReadAll()
strError = objExec.StdErr.ReadAll()

If strError = "" Then
    WScript.Echo "[SUCCESS] Startup task created successfully!"
Else
    WScript.Echo "[ERROR] Failed to create startup task:"
    WScript.Echo strError
End If

WScript.Echo ""

' Create daily backup task as fallback
WScript.Echo "Creating daily backup task (fallback)..."

strDailyCommand = "schtasks /create /tn """ & strTaskName & " (Daily)"" " & _
                  "/tr """ & strBatchFile & """ " & _
                  "/sc daily /st 02:00 " & _
                  "/ru SYSTEM " & _
                  "/rl highest " & _
                  "/f"

Set objDailyExec = objShell.Exec(strDailyCommand)
strDailyOutput = objDailyExec.StdOut.ReadAll()
strDailyError = objDailyExec.StdErr.ReadAll()

If strDailyError = "" Then
    WScript.Echo "[SUCCESS] Daily backup task created successfully!"
Else
    WScript.Echo "[ERROR] Failed to create daily task:"
    WScript.Echo strDailyError
End If

WScript.Echo ""
WScript.Echo "=========================================="
WScript.Echo "  Installation Complete!"
WScript.Echo "=========================================="
WScript.Echo ""
WScript.Echo "Tasks created:"
WScript.Echo "  - " & strTaskName & " (runs on system startup)"
WScript.Echo "  - " & strTaskName & " (Daily) (runs daily at 2:00 AM)"
WScript.Echo ""
WScript.Echo "Backups will be saved to: E:\MySQL_Backups\"
WScript.Echo ""
WScript.Echo "Press any key to exit..."

' Wait for key press
Set objInput = WScript.CreateObject("WScript.Shell")
objInput.Run "cmd /c pause", 1, True