// ver: 1.0.1
document.addEventListener('DOMContentLoaded', function() {
    // Check if the 'orgchart_ajax' object is available. This object is
    // created by WordPress's wp_localize_script and contains data
    // passed from PHP (like AJAX URL, nonce, and initial data).
    if (typeof orgchart_ajax === 'undefined') {
        console.error('Error: orgchart_ajax object not found. Please ensure wp_localize_script is working correctly.');
        return; // Exit if critical data is missing.
    }

    // Initialize the OrgChart instance.
    // The first argument is the DOM element where the chart will be rendered.
    // The second argument is an object containing chart configuration options.
    let chart = new OrgChart(document.getElementById("tree"), {
        mouseScrool: OrgChart.action.scroll, // Allows mouse wheel scrolling/zooming within the chart area.
        // Define the context menu that appears when a node is right-clicked.
        nodeMenu: {
            add: {
                text: "Add New"
            }, // Option to add a new node.
            edit: {
                text: "Edit"
            }, // Option to edit the current node.
            remove: {
                text: "Remove"
            } // Option to remove the current node.
        },
        // Configure the edit form that appears when 'Edit' is selected.
        editForm: {
            photoBinding: "ImgUrl", // Binds the photo field in the form to the 'ImgUrl' property of the node data.
            elements: [
                // Define form elements for editing node properties.
                {
                    type: 'textbox',
                    label: 'Photo Url',
                    binding: 'ImgUrl',
                    btn: 'Upload'
                }, // Textbox for image URL with an upload button.
                {
                    type: 'textbox',
                    label: 'Name',
                    binding: 'EmployeeName'
                }, {
                    type: 'textbox',
                    label: 'Title',
                    binding: 'Title'
                }, {
                    type: 'textbox',
                    label: 'Email',
                    binding: 'Email'
                }
            ]
        },
        // Define how node data properties are mapped to the visual fields in the chart.
        nodeBinding: {
            field_0: "EmployeeName", // Maps 'EmployeeName' to the first text field on the node.
            field_1: "Title", // Maps 'Title' to the second text field on the node.
            img_0: "ImgUrl" // Maps 'ImgUrl' to the image display on the node.
        }
    });

    /**
     * Asynchronously sends OrgChart data updates to the WordPress AJAX endpoint.
     *
     * @param {string} actionType - The type of action: 'add', 'update', or 'remove'.
     * @param {object} data - The node data object (e.g., newData for update, data for add).
     * @param {string|null} nodeId - The ID of the node to be removed (only for 'remove' action).
     */
    async function sendOrgChartData(actionType, data, nodeId = null) {
        // Create a FormData object to send data as key-value pairs, suitable for POST requests.
        const formData = new FormData();
        formData.append('action', 'orgchart_update'); // This is the WordPress AJAX action hook.
        formData.append('nonce', orgchart_ajax.nonce); // Include the security nonce.
        formData.append('action_type', actionType); // Specify the type of CRUD operation.
        formData.append('data', JSON.stringify(data)); // Stringify the node data to JSON.

        // If a nodeId is provided (typically for 'remove' operations), append it.
        if (nodeId) {
            formData.append('node_id', nodeId);
        }

        try {
            // Send the request to the WordPress AJAX URL using fetch.
            const response = await fetch(orgchart_ajax.ajax_url, {
                method: 'POST', // Use POST method for data submission.
                body: formData // The FormData object as the request body.
            });
            // Parse the JSON response from the server.
            const result = await response.json();

            // Check if the operation was successful based on the server's response.
            if (result.success) {
                console.log('OrgChart data updated successfully:', result.message);
                // In a more complex app, you might re-load data here if server assigns new IDs,
                // but OrgChart.js handles ID generation for new nodes well client-side.
            } else {
                console.error('Error updating OrgChart data:', result.message);
                // Inform the user about the error.
                alert('Error: ' + result.message + ' Please check console for details.');
            }
        } catch (error) {
            // Catch any network or parsing errors during the fetch operation.
            console.error('Fetch error during OrgChart data update:', error);
            alert('A network error occurred while trying to update OrgChart data.');
        }
    }

    // Event listener for when a node's data is updated.
    chart.onUpdateNode(function(args) {
        console.log("OrgChart: Node updated. Sending data to server...", args.newData);
        sendOrgChartData('update', args.newData); // Send the new node data to the server.
    });

    // Event listener for when a node is removed.
    chart.onRemoveNode(function(args) {
        console.log("OrgChart: Node removed. Sending ID to server...", args.id);
        sendOrgChartData('remove', {}, args.id); // Send an empty data object but pass the ID.
    });

    // Event listener for when a new node is added.
    chart.onAddNode(function(args) {
        console.log("OrgChart: Node added. Sending data to server...", args.data);
        sendOrgChartData('add', args.data); // Send the new node's data to the server.
    });


    // Event listener for the 'Upload' button click within the edit form.
    chart.editUI.on('element-btn-click', function(sender, args) {
        OrgChart.fileUploadDialog(function(file) {
            // Create a new FormData object for the file upload.
            const formData = new FormData();
            
            // Append the necessary data for WordPress AJAX handling.
            formData.append('action', 'upload_image_to_media'); // WP AJAX action for the upload.
            formData.append('nonce', orgchart_ajax.nonce);       // Security nonce.
            formData.append('file', file);                       // The actual file to upload.

            // Use fetch to send the file to the WordPress AJAX endpoint.
            fetch(orgchart_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(responseData => {
                if (responseData.success) {
                    // On success, update the form input and the node's avatar.
                    args.input.value = responseData.url;
                    chart.editUI.setAvatar(responseData.url)
// 
                } else {
                    // Handle errors returned from the server.
                    console.error('Error uploading file:', responseData.message);
                    alert('Error: ' + responseData.message);
                }
            })
            .catch(error => {
                // Handle network errors.
                console.error('Fetch error during file upload:', error);
                alert('A network error occurred while trying to upload the image.');
            });
        });
    });

    // Load initial data into the OrgChart.
    // The 'orgchart_ajax.initial_data' comes from the WordPress database.
    const initialData = JSON.parse(orgchart_ajax.initial_data);
    if (initialData && initialData.length > 0) {
        chart.load(initialData); // Load existing data.
    } else {
        // If no data is found in the database, load a default placeholder node.
        chart.load([{
            id: "1",
            EmployeeName: "New Employee",
            Title: "Click to Edit",
            ImgUrl: "[https://cdn.balkan.app/shared/empty.jpg](https://cdn.balkan.app/shared/empty.jpg)"
        }]);
    }
});
