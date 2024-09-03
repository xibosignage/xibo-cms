/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */
import { ClassicEditor as ClassicEditorBase } from '@ckeditor/ckeditor5-editor-classic';
import { InlineEditor as InlineEditorBase } from '@ckeditor/ckeditor5-editor-inline';
declare class ClassicEditor extends ClassicEditorBase {
}
declare class InlineEditor extends InlineEditorBase {
}
declare const _default: {
    ClassicEditor: typeof ClassicEditor;
    InlineEditor: typeof InlineEditor;
};
export default _default;
