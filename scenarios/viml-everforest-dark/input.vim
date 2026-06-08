function! ToggleNumber() abort
  if &number
    set nonumber
  else
    set number
  endif
endfunction

nnoremap <silent> <leader>n :call ToggleNumber()<CR>

let g:my_plugin_enabled = 1
let s:counter = 0

augroup MyGroup
  autocmd!
  autocmd BufWritePre *.py call s:StripTrailing()
augroup END
