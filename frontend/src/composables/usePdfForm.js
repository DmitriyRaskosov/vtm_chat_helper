import { computed, ref } from 'vue';
import fontkit from '@pdf-lib/fontkit';
import {
    PDFCheckBox,
    PDFDocument,
    PDFDropdown,
    PDFOptionList,
    PDFRadioGroup,
    PDFTextField,
} from 'pdf-lib';
import ptSansUrl from '../assets/fonts/PTSans-Regular.ttf?url';

let fontBytesPromise = null;

export function usePdfForm() {
    const fields = ref([]);
    const loading = ref(false);
    const error = ref('');
    const hasFields = computed(() => fields.value.length > 0);

    let sourceBytes = null;

    async function load(source) {
        loading.value = true;
        reset();

        try {
            const bytes = await toBytes(source);
            const document = await PDFDocument.load(bytes);
            sourceBytes = bytes;
            fields.value = document.getForm().getFields()
                .map(readField)
                .filter((field) => field !== null);

            return fields.value;
        } catch (err) {
            error.value = 'Не удалось прочитать PDF-форму.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function reset() {
        fields.value = [];
        sourceBytes = null;
        error.value = '';
    }

    function setField(name, value) {
        fields.value = fields.value.map((field) => (
            field.name === name ? { ...field, value } : field
        ));
    }

    async function toFile(filename = 'sheet.pdf') {
        if (sourceBytes === null) {
            throw new Error('PDF не загружен.');
        }

        error.value = '';

        try {
            const document = await PDFDocument.load(sourceBytes);
            const form = document.getForm();
            document.registerFontkit(fontkit);
            const font = await document.embedFont(await cyrillicFontBytes(), { subset: false });

            for (const field of fields.value) {
                writeField(form.getField(field.name), field.value);
            }

            form.updateFieldAppearances(font);

            const saved = await document.save();

            return new File([saved], filename, { type: 'application/pdf' });
        } catch (err) {
            error.value = 'Не удалось сохранить PDF-форму.';
            throw err;
        }
    }

    return {
        fields,
        hasFields,
        loading,
        error,
        load,
        reset,
        setField,
        toFile,
    };
}

function cyrillicFontBytes() {
    if (fontBytesPromise === null) {
        fontBytesPromise = fetch(ptSansUrl)
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Не удалось загрузить шрифт.');
                }

                return response.arrayBuffer();
            })
            .then((buffer) => new Uint8Array(buffer))
            .catch((err) => {
                fontBytesPromise = null;
                throw err;
            });
    }

    return fontBytesPromise;
}

async function toBytes(source) {
    if (source instanceof Blob) {
        return new Uint8Array(await source.arrayBuffer());
    }

    if (source instanceof ArrayBuffer) {
        return new Uint8Array(source);
    }

    if (source instanceof Uint8Array) {
        return new Uint8Array(source);
    }

    throw new Error('Не удалось прочитать PDF.');
}

function readField(field) {
    if (field instanceof PDFTextField) {
        return {
            name: field.getName(),
            type: 'text',
            value: field.getText() ?? '',
            options: [],
        };
    }

    if (field instanceof PDFCheckBox) {
        return {
            name: field.getName(),
            type: 'checkbox',
            value: field.isChecked(),
            options: [],
        };
    }

    if (field instanceof PDFDropdown) {
        const selected = field.getSelected();

        return {
            name: field.getName(),
            type: 'dropdown',
            value: selected[0] ?? '',
            options: field.getOptions(),
        };
    }

    if (field instanceof PDFRadioGroup) {
        return {
            name: field.getName(),
            type: 'radio',
            value: field.getSelected() ?? '',
            options: field.getOptions(),
        };
    }

    if (field instanceof PDFOptionList) {
        return {
            name: field.getName(),
            type: 'option-list',
            value: field.getSelected(),
            options: field.getOptions(),
        };
    }

    return null;
}

function writeField(field, value) {
    if (field instanceof PDFTextField) {
        field.setText(value == null ? '' : String(value));

        return;
    }

    if (field instanceof PDFCheckBox) {
        if (value) {
            field.check();
        } else {
            field.uncheck();
        }

        return;
    }

    if (field instanceof PDFDropdown || field instanceof PDFRadioGroup) {
        if (value == null || value === '') {
            field.clear();

            return;
        }

        field.select(String(value));

        return;
    }

    if (field instanceof PDFOptionList) {
        const selected = Array.isArray(value) ? value.map(String) : [];

        if (selected.length === 0) {
            field.clear();

            return;
        }

        field.select(selected);
    }
}
