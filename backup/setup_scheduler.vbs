' setup_scheduler.vbs
' Run this script as Administrator to setup automatic backup on system startup

Set objShell = CreateObject("Wscript.Shell")
strComputer = "."

' Get current directory
strCurrentDir = CreateObject("Scripting.FileSystemObject").GetAbsolutePathName(".")

' Create task in Windows Task Scheduler
strCommand = "schtasks /create /tn ""MySQL Database Backup"" /tr """ & strCurrentDir & "\run_backup.bat"" /sc onstart /ru SYSTEM /f"

' Execute command
Set objExec = objShell.Exec(strCommand)
strOutput = objExec.StdOut.ReadAll()
strError = objExec.StdErr.ReadAll()

If strError = "" Then
    Wscript.Echo "✓ Backup task created successfully!"
    Wscript.Echo "Task will run automatically on system startup."
Else
    Wscript.Echo "✗ Error creating task: " & strError
End If

' Optional: Also create a daily schedule as fallback
strDailyCommand = "schtasks /create /tn ""MySQL Database Backup Daily"" /tr """ & strCurrentDir & "\run_backup.bat"" /sc daily /st 03:00 /ru SYSTEM /f"
Set objDailyExec = objShell.Exec(strDailyCommand)