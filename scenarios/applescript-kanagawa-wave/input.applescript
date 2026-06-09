set fruitList to {"apple", "banana", "cherry"}
set total to 0

repeat with aFruit in fruitList
    log "fruit: " & aFruit
    set total to total + (count of aFruit)
end repeat

tell application "Finder"
    set fileCount to count of files in home folder
end tell

if total > 10 then
    display dialog "Long names, total " & total
else
    display dialog "Short"
end if
