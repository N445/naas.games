/*
 * Gallery editing for signed-in admins. Only loaded when the page is rendered for ROLE_ADMIN:
 * visitors never download this entry. Controllers join the front Stimulus application.
 */
import 'tom-select/dist/css/tom-select.css';
import './styles/front/curation.scss';
import { app } from './front/stimulus';
import TomSelectController from './admin/controllers/tom_select_controller';
import CurationEditorController from './curation/controllers/curation_editor_controller';
import CurationSelectionController from './curation/controllers/curation_selection_controller';

app.register('tom-select', TomSelectController);
app.register('curation-editor', CurationEditorController);
app.register('curation-selection', CurationSelectionController);
