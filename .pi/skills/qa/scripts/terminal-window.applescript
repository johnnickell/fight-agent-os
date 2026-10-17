-- Manage only the dedicated iTerm session returned by create
on dimensions(columnsText, rowsText)
  set columnCount to columnsText as integer
  set rowCount to rowsText as integer
  if columnCount < 1 or columnCount > 500 or rowCount < 1 or rowCount > 250 then
    error "Terminal dimensions must be 1-500 columns and 1-250 rows"
  end if
  return {columnCount, rowCount}
end dimensions

on identity(ownedWindow, ownedSession)
  tell application id "com.googlecode.iterm2"
    return (id of ownedWindow as text) & "|" & (id of ownedSession) & "|" & (tty of ownedSession) & "|" & (columns of ownedSession as text) & "|" & (rows of ownedSession as text)
  end tell
end identity

on run argv
  if (count argv) < 1 then error "Usage: create RUN_DIRECTORY COLUMNS ROWS | verify/close WINDOW_ID SESSION_ID | resize WINDOW_ID SESSION_ID COLUMNS ROWS"
  set operation to item 1 of argv
  if operation is "create" then
    if (count argv) is not 4 then error "Usage: create RUN_DIRECTORY COLUMNS ROWS"
    set runDirectory to item 2 of argv
    if runDirectory does not start with "/" then error "Run directory must be absolute"
    -- Require the caller's inspected launcher and disposable HOME before creating a window
    set launcherPath to runDirectory & "/launch.sh"
    set homePath to runDirectory & "/terminal-home"
    set launcherFile to POSIX file launcherPath as alias
    set homeDirectory to POSIX file homePath as alias
    set sizeValues to my dimensions(item 3 of argv, item 4 of argv)
    set commandText to "/usr/bin/env -i HOME=" & quoted form of homePath & " PATH=/usr/bin:/bin TERM=xterm-256color /bin/bash --noprofile --norc " & quoted form of launcherPath
    tell application id "com.googlecode.iterm2"
      set ownedWindow to create window with default profile command commandText
      set ownedSession to current session of ownedWindow
      tell ownedSession
        set columns to item 1 of sizeValues
        set rows to item 2 of sizeValues
      end tell
    end tell
    return my identity(ownedWindow, ownedSession)
  end if

  if operation is not in {"verify", "resize", "close"} then error "Unknown terminal operation"
  if operation is "resize" then
    if (count argv) is not 5 then error "Usage: resize WINDOW_ID SESSION_ID COLUMNS ROWS"
    set sizeValues to my dimensions(item 4 of argv, item 5 of argv)
  else
    if (count argv) is not 3 then error "Usage: verify/close WINDOW_ID SESSION_ID"
  end if
  set windowNumber to item 2 of argv as integer
  set sessionIdentity to item 3 of argv
  tell application id "com.googlecode.iterm2"
    if not (exists window id windowNumber) then
      if operation is "close" then return "already-closed"
      error "Owned QA window no longer exists; do not select another window"
    end if
    set ownedWindow to window id windowNumber
    set ownedSession to current session of ownedWindow
    if ownedSession is missing value then
      if operation is "close" then return "already-closed"
      error "Owned QA session has exited; do not select another session"
    end if
    if id of ownedSession is not sessionIdentity then error "QA session identity changed; refusing access"
    if operation is "close" then
      -- Close the owned session, preserving any other tabs added to this window
      tell ownedSession to close
      return "closed"
    end if
    if operation is "resize" then
      tell ownedSession
        set columns to item 1 of sizeValues
        set rows to item 2 of sizeValues
      end tell
    end if
  end tell
  return my identity(ownedWindow, ownedSession)
end run
