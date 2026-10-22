# Lead staged-tree receipt correction

The first staged-tree check passed all mapped/protected blob-mode comparisons, then failed comparing smart punctuation in the README line-map JSON because the host Python read_text default used the Windows locale. The code and staged tree were unchanged. The verification-only retry reads JSON explicitly as UTF-8, validates all six exact before/after lines and preserves every other README byte. The final receipt records that accepted staged tree.
