REPORT z_demo.
" Calculate a discounted total
DATA: lv_total TYPE p DECIMALS 2 VALUE '199.95',
      lv_name  TYPE string.

CONSTANTS gc_rate TYPE p DECIMALS 2 VALUE '0.10'.

lv_name = 'World'.
WRITE: / |Hello, { lv_name }!|.

IF lv_total > 100.
  lv_total = lv_total * ( 1 - gc_rate ).
  WRITE: / 'Discount applied:', lv_total.
ELSE.
  WRITE: / 'No discount'.
ENDIF.

LOOP AT itab INTO DATA(ls_row).
  WRITE: / ls_row-id, ls_row-text.
ENDLOOP.
