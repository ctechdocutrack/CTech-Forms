document.addEventListener('DOMContentLoaded', function () {
    const sectionsContainer = document.getElementById('sectionsContainer');
    const addSectionButton = document.getElementById('addSection');
    const addQuestionButton = document.getElementById('addQuestion');
    const titleInput = document.getElementById('form_title');
    const descriptionInput = document.getElementById('description');
    const previewTitle = document.getElementById('previewTitle');
    const previewDescription = document.getElementById('previewDescription');
    const previewQuestions = document.getElementById('previewQuestions');

    if (!sectionsContainer || !addSectionButton || !addQuestionButton) {
        console.error('Section builder elements were not found. Check create_forms.php IDs.');
        return;
    }

    let nextSectionId = 0;
    let nextQuestionId = 0;

    function createElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }

    function getSectionCards() {
        return Array.from(sectionsContainer.querySelectorAll('.section-card'));
    }

    function getQuestionCards() {
        return Array.from(sectionsContainer.querySelectorAll('.question-card'));
    }

    
    function reindexBuilder() {
        const sectionCards = getSectionCards();

        sectionCards.forEach((sectionCard, sectionIndex) => {
            // Preserve the existing section ID
            const sectionIdField = sectionCard.querySelector(
                '[data-section-field="section_id"]'
            );

            if (sectionIdField) {
                sectionIdField.name = `sections[${sectionIndex}][section_id]`;
            }

            // Section numbering
            sectionCard.querySelector('.section-number').textContent =
                'Section ' + (sectionIndex + 1);

            // Section title and description names
            sectionCard.querySelector('[data-section-field="title"]').name =
                `sections[${sectionIndex}][title]`;

            sectionCard.querySelector('[data-section-field="description"]').name =
                `sections[${sectionIndex}][description]`;

            // Delete section button
            const deleteSectionButton = sectionCard.querySelector('.delete-section');
            deleteSectionButton.disabled = sectionCards.length === 1;
            deleteSectionButton.title = sectionCards.length === 1
                ? 'At least one section is required'
                : 'Delete section';

            // Update question section indexes
            const questionCards = Array.from(
                sectionCard.querySelectorAll('.question-card')
            );

            questionCards.forEach(card => {
                card.dataset.sectionIndex = String(sectionIndex);
            });
        });

        // Reindex all questions
        getQuestionCards().forEach((card, questionIndex) => {
            const sectionIndex = Number(card.dataset.sectionIndex || 0);

            card.querySelector('.question-number').textContent =
                'Question ' + (questionIndex + 1);

            card.querySelector('.move-up').disabled =
                !card.previousElementSibling ||
                !card.previousElementSibling.classList.contains('question-card');

            card.querySelector('.move-down').disabled =
                !card.nextElementSibling ||
                !card.nextElementSibling.classList.contains('question-card');

            card.querySelectorAll('[data-field]').forEach(input => {
                input.name = `questions[${questionIndex}][${input.dataset.field}]`;
            });

            card.querySelectorAll('[data-option]').forEach(input => {
                input.name = `questions[${questionIndex}][options][]`;
            });

            const requiredToggle = card.querySelector('.required-toggle');
            requiredToggle.name = `questions[${questionIndex}][required]`;
            requiredToggle.value = '1';

            let sectionField = card.querySelector(
                '[data-field="section_index"]'
            );

            if (!sectionField) {
                sectionField = document.createElement('input');
                sectionField.type = 'hidden';
                sectionField.dataset.field = 'section_index';
                card.appendChild(sectionField);
            }

            sectionField.name = `questions[${questionIndex}][section_index]`;
            sectionField.value = String(sectionIndex);
        });

        updatePreview();
    }


    function createSection(savedSection = {}) {
        const sectionId = nextSectionId++;
        const card = createElement('div', 'card border shadow-sm mb-4 section-card');
        card.dataset.sectionId = String(sectionId);

        const header = createElement(
            'div',
            'card-header bg-white d-flex justify-content-between align-items-center py-3'
        );
        const number = createElement('strong', 'section-number', 'Section');
        const actions = createElement('div', 'd-flex gap-2');

        const deleteButton = createElement(
            'button',
            'btn btn-sm btn-outline-danger delete-section'
        );
        deleteButton.type = 'button';
        deleteButton.innerHTML = '<i class="bi bi-trash me-1"></i> Delete section';

        actions.appendChild(deleteButton);
        header.append(number, actions);

        const body = createElement('div', 'card-body p-4');
        const sectionIdField = createElement('input');
        sectionIdField.type = 'hidden';
        sectionIdField.dataset.sectionField = 'section_id';
        sectionIdField.value = savedSection.section_id || '';
        body.appendChild(sectionIdField);

        const titleLabel = createElement('label', 'form-label fw-semibold', 'Section title');
        const sectionTitle = createElement('input', 'form-control mb-3');
        sectionTitle.type = 'text';
        sectionTitle.maxLength = 255;
        sectionTitle.placeholder = 'Untitled section';
        sectionTitle.value = savedSection.section_title
            || savedSection.title
            || `Section ${sectionId + 1}`;
        sectionTitle.required = true;
        sectionTitle.dataset.sectionField = 'title';

        const descriptionLabel = createElement(
            'label',
            'form-label fw-semibold',
            'Section description (optional)'
        );
        const sectionDescription = createElement('textarea', 'form-control mb-3');
        sectionDescription.rows = 2;
        sectionDescription.placeholder = 'Add a description for this section';
        sectionDescription.value = savedSection.section_description
            || savedSection.description
            || '';
        sectionDescription.dataset.sectionField = 'description';

        const questionsArea = createElement('div', 'section-questions');
        const sectionAddQuestion = createElement(
            'button',
            'btn btn-sm btn-outline-primary mt-3'
        );
        sectionAddQuestion.type = 'button';
        sectionAddQuestion.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Add question to this section';

        body.append(
            titleLabel,
            sectionTitle,
            descriptionLabel,
            sectionDescription,
            questionsArea,
            sectionAddQuestion
        );
        card.append(header, body);
        sectionsContainer.appendChild(card);

        sectionTitle.addEventListener('input', updatePreview);
        sectionDescription.addEventListener('input', updatePreview);

        sectionAddQuestion.addEventListener('click', () => {
            createQuestion(card.querySelector('.section-questions'));
            reindexBuilder();
        });

        // deleteButton.addEventListener('click', () => {
        //     if (getSectionCards().length <= 1) return;

        //     const removedQuestions = card.querySelectorAll('.question-card');
        //     const fallbackSection = card.nextElementSibling || card.previousElementSibling;
        //     const fallbackQuestions = fallbackSection.querySelector('.section-questions');

        //     removedQuestions.forEach(question => fallbackQuestions.appendChild(question));
        //     card.remove();
        //     reindexBuilder();
        // });

        deleteButton.addEventListener('click', () => {
            if (getSectionCards().length <= 1) return;

            const confirmDelete = confirm(
                'Delete this section and all its questions? Save Changes to apply the deletion.'
            );

            if (!confirmDelete) return;

            card.remove();
            reindexBuilder();
        });

        reindexBuilder();
        return card;
    }

    function createQuestion(targetContainer, savedQuestion = {}) {
        const questionId = nextQuestionId++;
        const card = createElement('div', 'border rounded-3 p-3 mt-3 question-card bg-light');
        card.dataset.questionId = String(questionId);
        const savedQuestionId = createElement('input');
        savedQuestionId.type = 'hidden';
        savedQuestionId.dataset.field = 'question_id';
        savedQuestionId.value = savedQuestion.question_id || '';
        card.appendChild(savedQuestionId);

        const toolbar = createElement(
            'div',
            'd-flex justify-content-between align-items-center mb-3'
        );
        const number = createElement('strong', 'question-number', 'Question');
        const actions = createElement('div', 'd-flex gap-1');

        const up = createElement('button', 'btn btn-sm btn-light border move-up', '↑');
        up.type = 'button';
        up.title = 'Move up';
        const down = createElement('button', 'btn btn-sm btn-light border move-down', '↓');
        down.type = 'button';
        down.title = 'Move down';
        const duplicate = createElement('button', 'btn btn-sm btn-light border duplicate-question');
        duplicate.type = 'button';
        duplicate.title = 'Duplicate question';
        duplicate.innerHTML = '<i class="bi bi-copy"></i>';
        const remove = createElement('button', 'btn btn-sm btn-light border text-danger delete-question');
        remove.type = 'button';
        remove.title = 'Delete question';
        remove.innerHTML = '<i class="bi bi-x-lg"></i>';

        actions.append(up, down, duplicate, remove);
        toolbar.append(number, actions);

        const questionLabel = createElement('label', 'form-label fw-semibold', 'Question');
        const questionInput = createElement('input', 'form-control mb-3');
        questionInput.type = 'text';
        questionInput.placeholder = 'Untitled question';
        questionInput.value = savedQuestion.question_title || 'Untitled question';
        questionInput.maxLength = 500;
        questionInput.required = true;
        questionInput.dataset.field = 'title';

        const row = createElement('div', 'row g-3 align-items-end');
        const typeCol = createElement('div', 'col-12 col-md-8');
        const typeLabel = createElement('label', 'form-label fw-semibold', 'Answer type');
        const typeSelect = createElement('select', 'form-select');
        typeSelect.dataset.field = 'type';

        [
            ['short_answer', 'Short answer'],
            ['paragraph', 'Paragraph'],
            ['multiple_choice', 'Multiple choice'],
            ['checkboxes', 'Checkboxes'],
            ['dropdown', 'Dropdown']
        ].forEach(([value, label]) => {
            const option = createElement('option', '', label);
            option.value = value;
            typeSelect.appendChild(option);
        });
        typeCol.append(typeLabel, typeSelect);

        const requiredCol = createElement('div', 'col-12 col-md-4');
        const requiredWrapper = createElement('div', 'form-check mb-2');
        const required = createElement('input', 'form-check-input required-toggle');
        required.type = 'checkbox';
        required.id = 'required_' + questionId;
        required.value = '1';
        const requiredLabel = createElement('label', 'form-check-label fw-semibold', 'Required');
        requiredLabel.htmlFor = required.id;
        requiredWrapper.append(required, requiredLabel);
        requiredCol.appendChild(requiredWrapper);
        row.append(typeCol, requiredCol);

        const choicesArea = createElement('div', 'choice-editor mt-3 d-none');
        const choicesLabel = createElement('label', 'form-label fw-semibold', 'Answer options');
        const choicesList = createElement('div', 'choices-list');
        const addChoice = createElement('button', 'btn btn-sm btn-outline-secondary mt-2');
        addChoice.type = 'button';
        addChoice.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Add option';
        choicesArea.append(choicesLabel, choicesList, addChoice);

        card.append(toolbar, questionLabel, questionInput, row, choicesArea);
        targetContainer.appendChild(card);

        function addOption(value = '') {
            const optionRow = createElement('div', 'input-group mb-2 option-row');
            const input = createElement('input', 'form-control');
            input.type = 'text';
            input.placeholder = 'Option ' + (choicesList.children.length + 1);
            input.value = value;
            input.maxLength = 255;
            input.dataset.option = '1';

            const removeOption = createElement('button', 'btn btn-outline-secondary');
            removeOption.type = 'button';
            removeOption.title = 'Remove option';
            removeOption.innerHTML = '<i class="bi bi-x-lg"></i>';

            input.addEventListener('input', updatePreview);
            removeOption.addEventListener('click', () => {
                optionRow.remove();
                reindexBuilder();
            });

            optionRow.append(input, removeOption);
            choicesList.appendChild(optionRow);
            reindexBuilder();
        }

        function updateType() {
            const needsOptions = ['multiple_choice', 'checkboxes', 'dropdown']
                .includes(typeSelect.value);

            choicesArea.classList.toggle('d-none', !needsOptions);
            choicesList.querySelectorAll('input').forEach(input => {
                input.required = needsOptions;
                input.disabled = !needsOptions;
            });

            if (needsOptions && choicesList.children.length === 0) {
                ['Option 1', 'Option 2'].forEach(addOption);
            }

            updatePreview();
        }

        typeSelect.addEventListener('change', updateType);
        questionInput.addEventListener('input', updatePreview);
        required.addEventListener('change', updatePreview);
        addChoice.addEventListener('click', () => addOption());

        up.addEventListener('click', () => {
            const previous = card.previousElementSibling;
            if (previous && previous.classList.contains('question-card')) {
                card.parentElement.insertBefore(card, previous);
                reindexBuilder();
            }
        });

        down.addEventListener('click', () => {
            const next = card.nextElementSibling;
            if (next && next.classList.contains('question-card')) {
                card.parentElement.insertBefore(next, card);
                reindexBuilder();
            }
        });

        remove.addEventListener('click', () => {
            card.remove();
            reindexBuilder();
        });

        duplicate.addEventListener('click', () => {
            const clone = createQuestion(card.parentElement);
            clone.querySelector('[data-field="title"]').value = questionInput.value;
            clone.querySelector('[data-field="type"]').value = typeSelect.value;
            clone.querySelector('.required-toggle').checked = required.checked;

            const cloneList = clone.querySelector('.choices-list');
            cloneList.replaceChildren();
            choicesList.querySelectorAll('input').forEach(input => {
                const optionRow = createElement('div', 'input-group mb-2 option-row');
                const cloneInput = createElement('input', 'form-control');
                cloneInput.type = 'text';
                cloneInput.value = input.value;
                cloneInput.maxLength = 255;
                cloneInput.dataset.option = '1';
                cloneInput.addEventListener('input', updatePreview);

                const cloneRemove = createElement('button', 'btn btn-outline-secondary');
                cloneRemove.type = 'button';
                cloneRemove.innerHTML = '<i class="bi bi-x-lg"></i>';
                cloneRemove.addEventListener('click', () => {
                    optionRow.remove();
                    reindexBuilder();
                });

                optionRow.append(cloneInput, cloneRemove);
                cloneList.appendChild(optionRow);
            });

            clone.querySelector('[data-field="type"]').dispatchEvent(new Event('change'));
            card.parentElement.insertBefore(clone, card.nextSibling);
            reindexBuilder();
        });

        // Restore saved question values when editing
        if (savedQuestion.question_type) {
            typeSelect.value = savedQuestion.question_type;
        }

        required.checked = Number(savedQuestion.is_required) === 1;

        if (Array.isArray(savedQuestion.options)) {
            choicesList.replaceChildren();

            savedQuestion.options.forEach(option => {
                addOption(option);
            });
        }

        updateType();
        reindexBuilder();

        return card;
    }

    function updatePreview() {
        if (previewTitle && titleInput) {
            previewTitle.textContent = titleInput.value.trim() || 'Untitled form';
        }

        if (previewDescription && descriptionInput) {
            previewDescription.textContent = descriptionInput.value;
        }

        if (!previewQuestions) return;
        previewQuestions.replaceChildren();

        getSectionCards().forEach((sectionCard, sectionIndex) => {
            const sectionTitle = sectionCard.querySelector('[data-section-field="title"]').value.trim()
                || `Section ${sectionIndex + 1}`;
            const sectionDescription = sectionCard.querySelector('[data-section-field="description"]').value.trim();

            const sectionPreview = createElement('div', 'p-3 border-top');
            const heading = createElement('h6', 'fw-bold mb-1', sectionTitle);
            sectionPreview.appendChild(heading);

            if (sectionDescription) {
                sectionPreview.appendChild(
                    createElement('p', 'small text-secondary mb-3', sectionDescription)
                );
            }

            const questions = sectionCard.querySelectorAll('.question-card');
            if (questions.length === 0) {
                sectionPreview.appendChild(
                    createElement('p', 'small text-secondary mb-0', 'No questions in this section yet.')
                );
            }

            questions.forEach((card, index) => {
                const questionTitle = card.querySelector('[data-field="title"]').value.trim()
                    || `Question ${index + 1}`;
                const questionType = card.querySelector('[data-field="type"]').value;
                const isRequired = card.querySelector('.required-toggle').checked;
                const item = createElement('div', 'mb-3');

                const label = createElement(
                    'div',
                    'small fw-semibold mb-1',
                    questionTitle + (isRequired ? ' *' : '')
                );
                item.appendChild(label);

                if (['multiple_choice', 'checkboxes', 'dropdown'].includes(questionType)) {
                    const options = Array.from(card.querySelectorAll('[data-option]'))
                        .filter(input => !input.disabled && input.value.trim() !== '');

                    if (questionType === 'dropdown') {
                        const select = createElement('select', 'form-select form-select-sm');
                        select.disabled = true;
                        select.appendChild(createElement('option', '', 'Choose an option'));
                        options.forEach(input => select.appendChild(
                            createElement('option', '', input.value)
                        ));
                        item.appendChild(select);
                    } else {
                        options.forEach(input => {
                            const line = createElement('div', 'form-check');
                            const control = createElement('input', 'form-check-input');
                            control.type = questionType === 'multiple_choice' ? 'radio' : 'checkbox';
                            control.disabled = true;
                            const optionLabel = createElement('label', 'form-check-label small', input.value);
                            line.append(control, optionLabel);
                            item.appendChild(line);
                        });
                    }
                } else if (questionType === 'paragraph') {
                    const answer = createElement('textarea', 'form-control form-control-sm');
                    answer.rows = 2;
                    answer.placeholder = 'Long answer text';
                    answer.disabled = true;
                    item.appendChild(answer);
                } else {
                    const answer = createElement('input', 'form-control form-control-sm');
                    answer.placeholder = 'Short answer text';
                    answer.disabled = true;
                    item.appendChild(answer);
                }

                sectionPreview.appendChild(item);
            });

            previewQuestions.appendChild(sectionPreview);
        });
    }

    addSectionButton.addEventListener('click', () => {
        createSection();
        reindexBuilder();
    });

    addQuestionButton.addEventListener('click', () => {
        const sections = getSectionCards();
        const targetSection = sections[sections.length - 1];
        createQuestion(targetSection.querySelector('.section-questions'));
        reindexBuilder();
    });

    if (titleInput) titleInput.addEventListener('input', updatePreview);
    if (descriptionInput) descriptionInput.addEventListener('input', updatePreview);

    // Load saved sections and questions when editing a form
    const savedSections = Array.isArray(window.savedSections)
        ? window.savedSections
        : [];

    const savedQuestions = Array.isArray(window.savedQuestions)
        ? window.savedQuestions
        : [];

    if (savedSections.length > 0) {
        const sectionCardsById = new Map();

        // Create the existing sections first
        savedSections.forEach(savedSection => {
            const sectionCard = createSection(savedSection);

            sectionCardsById.set(
                String(savedSection.section_id),
                sectionCard
            );
        });

        // Place each question in its saved section
        savedQuestions.forEach(savedQuestion => {
            const sectionCard = sectionCardsById.get(
                String(savedQuestion.section_id)
            );

            const targetSection = sectionCard || getSectionCards()[0];

            if (targetSection) {
                createQuestion(
                    targetSection.querySelector('.section-questions'),
                    savedQuestion
                );
            }
        });
    } else {
        // New form: create the default section and question
        const firstSection = createSection({
            title: 'Section 1',
            description: ''
        });

        createQuestion(
            firstSection.querySelector('.section-questions')
        );
    }

    reindexBuilder();
});
