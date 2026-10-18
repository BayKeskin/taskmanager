/**
 * AiArchitect Module Client Interactions
 */
window.AiArchitect = {
    async refineSpecification(projectId, instruction, images = [], codeFiles = []) {
        const formData = new FormData();
        formData.append('project_id', projectId);
        formData.append('instruction', instruction);

        if (images && images.length) {
            for (let i = 0; i < images.length; i++) {
                formData.append('images[]', images[i]);
            }
        }
        if (codeFiles && codeFiles.length) {
            for (let i = 0; i < codeFiles.length; i++) {
                formData.append('code_files[]', codeFiles[i]);
            }
        }

        const res = await fetch('/taskmanager/ajax?action=refine_task_specification', {
            method: 'POST',
            body: formData
        });
        return await res.json();
    }
};
