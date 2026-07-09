import { useState, useCallback } from 'react'
import axios from 'axios'
import { useDropzone, type FileRejection, type DropzoneOptions } from 'react-dropzone'
import { toast } from 'sonner'

export type UploadedFile = File & {
    preview: string
    errors: Array<{ message: string; code: string }>
}

export type UseUploadReturn = {
    files: UploadedFile[]
    setFiles: (files: UploadedFile[]) => void
    onUpload: () => Promise<string[]>
    loading: boolean
    successes: string[]
    errors: Array<{ name: string; message: string }>
    maxFileSize: number
    maxFiles: number
    isSuccess: boolean
    isDragActive: boolean
    isDragReject: boolean
    getRootProps: (props?: any) => any
    getInputProps: (props?: any) => any
    inputRef: React.RefObject<HTMLInputElement>
}

interface UseUploadProps extends DropzoneOptions {
    maxFiles?: number
    maxFileSize?: number // in bytes
}

// Default to localhost, but this should be configurable
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || (import.meta.env.DEV ? 'https://saddlebrown-badger-327057.hostingersite.com/backend' : '/backend');
const UPLOAD_URL = `${API_BASE_URL}/upload.php`;

export const useUpload = ({
    maxFiles = 1,
    maxFileSize = 5 * 1024 * 1024, // 5MB
    ...dropzoneOptions
}: UseUploadProps = {}) => {
    const [files, setFiles] = useState<UploadedFile[]>([])
    const [loading, setLoading] = useState(false)
    const [successes, setSuccesses] = useState<string[]>([])
    const [errors, setErrors] = useState<Array<{ name: string; message: string }>>([])
    const [isSuccess, setIsSuccess] = useState(false)

    const onDrop = useCallback((acceptedFiles: File[], fileRejections: FileRejection[]) => {
        const newFiles = acceptedFiles.map((file) =>
            Object.assign(file, {
                preview: URL.createObjectURL(file),
                errors: [],
            })
        ) as UploadedFile[]

        const rejectedFiles = fileRejections.map(({ file, errors }) =>
            Object.assign(file, {
                preview: URL.createObjectURL(file), // might not be safe for all rejections but ok for now
                errors: errors.map((e) => ({ message: e.message, code: e.code })),
            })
        ) as UploadedFile[]

        setFiles((prev) => [...prev, ...newFiles, ...rejectedFiles].slice(0, maxFiles))
        setIsSuccess(false)
        setErrors([])
        setSuccesses([])
    }, [maxFiles])

    const { getRootProps, getInputProps, isDragActive, isDragReject, inputRef } = useDropzone({
        onDrop,
        maxFiles,
        maxSize: maxFileSize,
        accept: {
            'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.webp'],
        },
        ...dropzoneOptions,
    })

    const onUpload = useCallback(async () => {
        setLoading(true)
        setErrors([])
        setSuccesses([])
        const uploadedUrls: string[] = []

        try {
            // Filter out files with errors
            const validFiles = files.filter(f => f.errors.length === 0)

            if (validFiles.length === 0) {
                toast.error("No valid files to upload")
                setLoading(false)
                return []
            }

            for (const file of validFiles) {
                const formData = new FormData()
                formData.append('file', file)

                try {
                    const response = await axios.post(UPLOAD_URL, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                        withCredentials: true,
                    })

                    if (response.data.success) {
                        uploadedUrls.push(response.data.url)
                        setSuccesses(prev => [...prev, file.name])
                    } else {
                        setErrors(prev => [...prev, { name: file.name, message: response.data.message || 'Upload failed' }])
                    }
                } catch (err: any) {
                    setErrors(prev => [...prev, { name: file.name, message: err.message || 'Network error' }])
                }
            }

            if (uploadedUrls.length === validFiles.length) {
                setIsSuccess(true)
                toast.success("All files uploaded successfully")
            } else if (uploadedUrls.length > 0) {
                toast.warning("Some files failed to upload")
            }

        } catch (error) {
            console.error("Upload error:", error)
            toast.error("An unexpected error occurred")
        }
        setLoading(false)
        return uploadedUrls
    }, [files])

    return {
        files,
        setFiles,
        onUpload,
        loading,
        successes,
        errors,
        maxFileSize,
        maxFiles,
        isSuccess,
        getRootProps,
        getInputProps,
        isDragActive,
        isDragReject,
        inputRef
    }
}
