function fish_greeting
    set_color cyan
    echo "Welcome, "(whoami)
    set_color normal
end

function backup --argument-names src
    if test -d $src
        for f in $src/*.txt
            cp $f $f.bak
        end
    else
        echo "not a directory" >&2
        return 1
    end
end

set -gx PATH $HOME/bin $PATH
